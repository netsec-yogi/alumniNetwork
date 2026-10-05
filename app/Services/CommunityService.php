<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\AlumniProfile;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Notifications\CommunityJoinRequested;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Communities, chapters and batch groups (SRS 27-28). Who may moderate is
 * decided here once: global moderators (communities.moderate, or
 * chapters.manage for chapters) plus a group's own admins and moderators.
 */
class CommunityService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function join(User $user, Community $community): CommunityMember
    {
        if (! $user->isCommunityMember()) {
            throw new InvalidArgumentException('Only verified members can join groups.');
        }

        $membership = CommunityMember::firstOrNew(['community_id' => $community->id, 'user_id' => $user->id]);
        if ($membership->exists) {
            throw new InvalidArgumentException(match ($membership->status) {
                CommunityMember::BANNED => 'You can’t join this group.',
                CommunityMember::PENDING => 'Your request is waiting for approval.',
                default => 'You’re already a member.',
            });
        }

        if (! $community->admits($user)) {
            throw new InvalidArgumentException('This group is only for members of its batch.');
        }

        $membership->forceFill([
            'role' => CommunityMember::MEMBER,
            'status' => $community->join_policy === Community::APPROVAL ? CommunityMember::PENDING : CommunityMember::ACTIVE,
        ])->save();

        if ($membership->status === CommunityMember::PENDING) {
            Notification::send($this->admins($community), new CommunityJoinRequested($community, $user));
        }

        return $membership;
    }

    public function leave(User $user, Community $community): void
    {
        $membership = $community->membershipOf($user);
        if ($membership === null || $membership->status === CommunityMember::BANNED) {
            return;
        }
        if ($membership->role === CommunityMember::ADMIN && $community->memberships()->where('role', CommunityMember::ADMIN)->where('status', CommunityMember::ACTIVE)->count() === 1) {
            throw new InvalidArgumentException('You are the only admin. Make someone else an admin before leaving.');
        }

        $membership->delete();
    }

    /** approve | reject | promote | demote | make_admin | ban | remove */
    public function manage(User $actor, CommunityMember $member, string $action): void
    {
        $community = $member->community;
        $isAdmin = $this->isAdmin($actor, $community);

        if ($member->user_id === $actor->id) {
            throw new InvalidArgumentException('You can’t change your own membership here.');
        }
        if (in_array($action, ['promote', 'demote', 'make_admin'], true) && ! $isAdmin) {
            throw new InvalidArgumentException('Only group admins can change roles.');
        }
        if (! $this->canModerate($actor, $community)) {
            throw new InvalidArgumentException('Not allowed.');
        }
        // Moderators can't act on admins or other moderators.
        if (! $isAdmin && $member->role !== CommunityMember::MEMBER) {
            throw new InvalidArgumentException('Only admins can act on moderators and admins.');
        }

        match ($action) {
            'approve' => $member->forceFill(['status' => CommunityMember::ACTIVE])->save(),
            'reject', 'remove' => $member->delete(),
            'promote' => $member->forceFill(['role' => CommunityMember::MODERATOR])->save(),
            'demote' => $member->forceFill(['role' => CommunityMember::MEMBER])->save(),
            'make_admin' => $member->forceFill(['role' => CommunityMember::ADMIN, 'status' => CommunityMember::ACTIVE])->save(),
            'ban' => $member->forceFill(['status' => CommunityMember::BANNED, 'role' => CommunityMember::MEMBER])->save(),
        };

        $this->audit->record("community.member_{$action}", 'communities', $community, null, ['user_id' => $member->user_id], $actor);
    }

    public function isGlobalModerator(User $user, Community $community): bool
    {
        return $user->can(Permission::CommunitiesModerate->value)
            || ($community->isChapter() && $user->can(Permission::ChaptersManage->value));
    }

    public function isAdmin(User $user, Community $community): bool
    {
        return $this->isGlobalModerator($user, $community)
            || $community->memberships()->where(['user_id' => $user->id, 'role' => CommunityMember::ADMIN, 'status' => CommunityMember::ACTIVE])->exists();
    }

    public function canModerate(User $user, Community $community): bool
    {
        return $this->isGlobalModerator($user, $community) || (bool) $community->membershipOf($user)?->canModerate();
    }

    public function isActiveMember(User $user, Community $community): bool
    {
        return (bool) $community->membershipOf($user)?->isActive();
    }

    /** Readable by everyone in the network (open groups), or only by members. */
    public function canRead(User $user, Community $community): bool
    {
        if (! $user->isCommunityMember()) {
            return false;
        }

        return $community->join_policy === Community::OPEN
            || $this->isActiveMember($user, $community)
            || $this->isGlobalModerator($user, $community);
    }

    /** @return Collection<int, int> ids of communities whose posts this user may read */
    public function readableIds(User $user): Collection
    {
        return Community::query()
            ->where('join_policy', Community::OPEN)
            ->orWhereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', CommunityMember::ACTIVE))
            ->pluck('id');
    }

    /**
     * Batch groups (module 12): every verified alumnus is placed in the group
     * for their programme and graduation year, created on first need.
     */
    public function enrolInBatchGroup(AlumniProfile $profile): void
    {
        $profile->loadMissing('programme', 'user');
        $eligibility = ['programme_id' => $profile->programme_id, 'graduation_year' => $profile->graduation_year];

        $group = Community::where('category', 'batch')->where('join_policy', Community::RESTRICTED)
            ->where('eligibility->programme_id', $profile->programme_id)
            ->where('eligibility->graduation_year', $profile->graduation_year)
            ->first();

        if ($group === null) {
            $group = new Community([
                'kind' => Community::KIND_COMMUNITY,
                'category' => 'batch',
                'name' => "{$profile->programme->code} · Batch of {$profile->graduation_year}",
                'description' => "The official group for {$profile->programme->name} graduates of {$profile->graduation_year}.",
                'join_policy' => Community::RESTRICTED,
                'is_official' => true,
            ]);
            $group->forceFill(['eligibility' => $eligibility])->save();
        }

        // Existing rows are left alone: never quietly un-ban someone.
        $membership = CommunityMember::firstOrNew(['community_id' => $group->id, 'user_id' => $profile->user_id]);
        if (! $membership->exists) {
            $membership->forceFill(['role' => CommunityMember::MEMBER, 'status' => CommunityMember::ACTIVE])->save();
        }
    }

    /** @return Collection<int, User> */
    private function admins(Community $community): Collection
    {
        return $community->members()->wherePivot('role', CommunityMember::ADMIN)->get();
    }
}
