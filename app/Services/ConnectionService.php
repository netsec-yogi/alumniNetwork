<?php

namespace App\Services;

use App\Models\AlumniProfile;
use App\Models\Connection;
use App\Models\EngagementActivity;
use App\Models\User;
use App\Notifications\ConnectionAccepted;
use App\Notifications\ConnectionRequested;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Connections, blocks and follows (SRS 24).
 *
 * Rules: one row per pair; nobody can connect with, follow or be contacted
 * by someone who blocked them (or whom they blocked); blocking removes any
 * connection and follow in both directions.
 */
class ConnectionService
{
    /** @var array<int, Collection<int, int>> per-request cache of connected ids */
    private array $connectedCache = [];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EngagementRecorder $engagement,
    ) {}

    public function request(User $from, User $to, ?string $message = null): Connection
    {
        $this->guard($from, $to);

        if (Connection::between($from->id, $to->id)->exists()) {
            throw new InvalidArgumentException('You are already connected, or a request is pending.');
        }

        $connection = new Connection;
        $connection->forceFill([
            'requester_id' => $from->id,
            'addressee_id' => $to->id,
            'status' => Connection::PENDING,
            'message' => $message,
        ])->save();

        $to->notify(new ConnectionRequested($from, $connection));

        return $connection;
    }

    public function accept(User $actor, Connection $connection): void
    {
        $this->assertAddressee($actor, $connection);

        DB::transaction(function () use ($connection) {
            $connection->forceFill(['status' => Connection::ACCEPTED, 'responded_at' => now()])->save();
        });
        $this->forget($connection->requester_id, $connection->addressee_id);

        foreach ([$connection->requester, $connection->addressee] as $party) {
            $this->engagement->record($party, 'CONNECTION_CREATED', EngagementActivity::MODE_EXPERIENTIAL, $connection);
        }

        $connection->requester->notify(new ConnectionAccepted($actor));
    }

    /** Decline a received request. No notification: declining is quiet. */
    public function decline(User $actor, Connection $connection): void
    {
        $this->assertAddressee($actor, $connection);
        $connection->delete();
    }

    /** Withdraw a sent request, or remove an accepted connection. */
    public function remove(User $actor, Connection $connection): void
    {
        if (! in_array($actor->id, [$connection->requester_id, $connection->addressee_id], true)) {
            throw new InvalidArgumentException('Not your connection.');
        }

        $connection->delete();
        $this->forget($connection->requester_id, $connection->addressee_id);
    }

    public function block(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new InvalidArgumentException('You cannot block yourself.');
        }

        DB::transaction(function () use ($actor, $target) {
            $actor->blocks()->syncWithoutDetaching([$target->id]);
            Connection::between($actor->id, $target->id)->delete();
            DB::table('follows')->where(fn ($q) => $q->where(['follower_id' => $actor->id, 'followed_id' => $target->id])
                ->orWhere(fn ($q) => $q->where(['follower_id' => $target->id, 'followed_id' => $actor->id])))->delete();
        });
        $this->forget($actor->id, $target->id);

        $this->audit->record('user.blocked', 'connect', $target, null, null, $actor);
    }

    public function unblock(User $actor, User $target): void
    {
        $actor->blocks()->detach($target->id);
    }

    public function follow(User $actor, User $target): void
    {
        $this->guard($actor, $target);
        $actor->following()->syncWithoutDetaching([$target->id]);
    }

    public function unfollow(User $actor, User $target): void
    {
        $actor->following()->detach($target->id);
    }

    public function isBlockedEitherWay(int $a, int $b): bool
    {
        return DB::table('user_blocks')
            ->where(fn ($q) => $q->where(['blocker_id' => $a, 'blocked_id' => $b])
                ->orWhere(fn ($q) => $q->where(['blocker_id' => $b, 'blocked_id' => $a])))
            ->exists();
    }

    /** @return Collection<int, int> ids of users blocked by, or blocking, $userId */
    public function blockedIds(int $userId): Collection
    {
        return DB::table('user_blocks')->where('blocker_id', $userId)->pluck('blocked_id')
            ->merge(DB::table('user_blocks')->where('blocked_id', $userId)->pluck('blocker_id'))
            ->unique()->values();
    }

    /** @return Collection<int, int> */
    public function connectedIds(int $userId): Collection
    {
        return $this->connectedCache[$userId] ??= Connection::query()
            ->involving($userId)
            ->where('status', Connection::ACCEPTED)
            ->get(['requester_id', 'addressee_id'])
            ->toBase() // plain ids: Eloquent collections compare items by model key
            ->map(fn (Connection $c) => $c->otherParty($userId))
            ->values();
    }

    public function areConnected(int $a, int $b): bool
    {
        return $this->connectedIds($a)->contains($b);
    }

    public function mutualCount(int $a, int $b): int
    {
        return $this->connectedIds($a)->intersect($this->connectedIds($b))->count();
    }

    /**
     * The relationship between viewer and subject, for rendering buttons.
     *
     * @return array{state: string, connection_id: int|null, following: bool, blocked: bool}
     */
    public function relationship(User $viewer, User $subject): array
    {
        $c = Connection::between($viewer->id, $subject->id)->first();

        $state = match (true) {
            $c === null => 'none',
            $c->status === Connection::ACCEPTED => 'connected',
            $c->requester_id === $viewer->id => 'sent',
            default => 'received',
        };

        return [
            'state' => $state,
            'connection_id' => $c?->id,
            'following' => $viewer->following()->whereKey($subject->id)->exists(),
            'blocked' => $viewer->blocks()->whereKey($subject->id)->exists(),
        ];
    }

    /**
     * People you may know: verified alumni from your own batch first, then
     * the same programme in neighbouring years. Only programme and batch are
     * used -- never privacy-controlled fields, which would leak through the
     * suggestion itself.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestions(User $viewer, int $limit = 6): array
    {
        $mine = $viewer->alumniProfile;
        if ($mine === null) {
            return [];
        }

        $exclude = Connection::involving($viewer->id)->get(['requester_id', 'addressee_id'])
            ->toBase()
            ->flatMap(fn (Connection $c) => [$c->requester_id, $c->addressee_id])
            ->merge($this->blockedIds($viewer->id))
            ->push($viewer->id)
            ->unique();

        return AlumniProfile::query()
            ->verified()
            ->whereNotIn('user_id', $exclude)
            ->where('programme_id', $mine->programme_id)
            ->whereBetween('graduation_year', [$mine->graduation_year - 2, $mine->graduation_year + 2])
            ->with(['user:id,name', 'programme:id,name'])
            ->orderByRaw('ABS(graduation_year - ?)', [$mine->graduation_year])
            ->limit($limit)
            ->get()
            ->map(fn (AlumniProfile $p) => [
                'profile_id' => $p->id,
                'name' => $p->displayName(),
                'subtitle' => "{$p->programme->name} · {$p->graduation_year}",
                'reason' => $p->graduation_year === $mine->graduation_year ? 'Your batch' : 'Your programme',
            ])
            ->all();
    }

    private function guard(User $from, User $to): void
    {
        if ($from->is($to)) {
            throw new InvalidArgumentException('You cannot connect with yourself.');
        }

        // Deliberately the same message either way: a blocked user must not
        // learn that they were blocked.
        if ($this->isBlockedEitherWay($from->id, $to->id) || ! $to->isActive()) {
            throw new InvalidArgumentException('You can’t connect with this member.');
        }
    }

    private function assertAddressee(User $actor, Connection $connection): void
    {
        if ($connection->addressee_id !== $actor->id || $connection->status !== Connection::PENDING) {
            throw new InvalidArgumentException('This request is no longer pending.');
        }
    }

    private function forget(int ...$ids): void
    {
        foreach ($ids as $id) {
            unset($this->connectedCache[$id]);
        }
    }
}
