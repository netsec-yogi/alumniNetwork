<?php

namespace Tests\Feature\Community;

use App\Enums\RoleName;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EngagementActivity;
use App\Models\Event;
use App\Models\Post;
use App\Models\Report;
use App\Services\AlumniVerificationService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunityFeedTest extends TestCase
{
    private function member(Community $c, $user, string $role = 'member', string $status = 'active'): void
    {
        CommunityMember::forceCreate(['community_id' => $c->id, 'user_id' => $user->id, 'role' => $role, 'status' => $status]);
    }

    public function test_verification_places_alumni_in_their_batch_group(): void
    {
        $a = $this->verifiedAlumnus(['verification_status' => 'pending', 'graduation_year' => 2016]);
        $b = $this->verifiedAlumnus(['verification_status' => 'pending', 'graduation_year' => 2016, 'programme_id' => $a->programme_id]);
        $service = app(AlumniVerificationService::class);
        $officer = $this->admin(RoleName::VerificationOfficer);

        $service->approve($service->submit($a), $officer);
        $service->approve($service->submit($b), $officer);

        $group = Community::where('category', 'batch')->sole();
        $this->assertSame(2, $group->members()->count());

        // Outsiders can't join or even see it.
        $outsider = $this->verifiedAlumnus(['graduation_year' => 2010]);
        $this->actingAs($outsider->user)->post(route('communities.join', $group))->assertSessionHas('error');
        $this->actingAs($outsider->user)->get(route('communities.show', $group))->assertForbidden();
    }

    public function test_open_and_approval_joining(): void
    {
        $open = Community::factory()->create();
        $closed = Community::factory()->approval()->create();
        $admin = $this->verifiedAlumnus()->user;
        $this->member($closed, $admin, 'admin');
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->post(route('communities.join', $open))->assertSessionHas('success');
        $this->actingAs($user)->post(route('communities.join', $closed));
        $pending = CommunityMember::where(['community_id' => $closed->id, 'user_id' => $user->id])->sole();
        $this->assertSame('pending', $pending->status);

        $this->actingAs($admin)->post(route('communities.members.manage', $pending), ['action' => 'approve'])->assertSessionHas('success');
        $this->assertSame('active', $pending->fresh()->status);
    }

    public function test_unverified_users_cannot_join_or_post(): void
    {
        $open = Community::factory()->create();
        $pending = $this->verifiedAlumnus(['verification_status' => 'pending'])->user;

        $this->actingAs($pending)->post(route('communities.join', $open))->assertSessionHas('error');
        $this->actingAs($pending)->post(route('posts.store'), ['body' => 'Hello'])->assertForbidden();
        $this->actingAs($pending)->get(route('feed'))->assertForbidden();
    }

    public function test_posts_in_member_only_groups_stay_inside_them(): void
    {
        $closed = Community::factory()->approval()->create();
        $insider = $this->verifiedAlumnus()->user;
        $this->member($closed, $insider);
        $outsider = $this->verifiedAlumnus()->user;

        $this->actingAs($insider)->post(route('posts.store'), ['body' => 'Members-only plans', 'community_id' => $closed->id])->assertSessionHas('success');
        $post = Post::sole();

        $this->actingAs($outsider)->get(route('posts.show', $post))->assertForbidden();
        $this->actingAs($outsider)->get(route('feed'))->assertInertia(fn (Assert $p) => $p->where('posts.data', []));
        $this->actingAs($outsider)->post(route('posts.store'), ['body' => 'Sneaky', 'community_id' => $closed->id])->assertSessionHas('error');
        $this->actingAs($insider)->get(route('feed'))->assertInertia(fn (Assert $p) => $p->has('posts.data', 1));
    }

    public function test_like_save_and_comment_update_counters(): void
    {
        $author = $this->verifiedAlumnus()->user;
        $post = Post::factory()->create(['user_id' => $author->id]);
        $fan = $this->verifiedAlumnus()->user;

        $this->actingAs($fan)->post(route('posts.like', $post));
        $this->actingAs($fan)->post(route('posts.like', $post)); // toggles off
        $this->actingAs($fan)->post(route('posts.like', $post));
        $this->actingAs($fan)->post(route('posts.save', $post));
        $this->actingAs($fan)->post(route('posts.comments.store', $post), ['body' => 'Congrats!']);

        $post->refresh();
        $this->assertSame(1, $post->likes_count);
        $this->assertSame(1, $post->comments_count);
        $this->actingAs($fan)->get(route('feed', ['tab' => 'saved']))->assertInertia(fn (Assert $p) => $p->has('posts.data', 1)->where('posts.data.0.liked', true));
    }

    public function test_only_author_or_moderator_deletes_and_moderators_remove_softly(): void
    {
        $community = Community::factory()->create();
        $author = $this->verifiedAlumnus()->user;
        $mod = $this->verifiedAlumnus()->user;
        $rando = $this->verifiedAlumnus()->user;
        $this->member($community, $author);
        $this->member($community, $mod, 'moderator');
        $post = Post::factory()->create(['user_id' => $author->id, 'community_id' => $community->id]);

        $this->actingAs($rando)->delete(route('posts.destroy', $post))->assertForbidden();
        $this->actingAs($mod)->delete(route('posts.destroy', $post));

        $this->assertSame(Post::REMOVED, $post->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.removed', 'entity_id' => $post->id]);
    }

    public function test_blocked_authors_vanish_from_feed(): void
    {
        $author = $this->verifiedAlumnus()->user;
        $viewer = $this->verifiedAlumnus()->user;
        Post::factory()->create(['user_id' => $author->id]);
        $viewer->blocks()->attach($author->id);

        $this->actingAs($viewer)->get(route('feed'))->assertInertia(fn (Assert $p) => $p->where('posts.data', []));
        $this->actingAs($author)->get(route('posts.show', Post::sole()))->assertOk(); // their own
    }

    public function test_announcements_are_only_for_moderators(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $this->actingAs($user)->post(route('posts.store'), ['body' => 'Big news', 'kind' => 'announcement']);
        $this->assertSame('post', Post::sole()->kind);
    }

    public function test_posting_records_engagement_and_links_must_be_https(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $this->actingAs($user)->post(route('posts.store'), ['body' => 'x', 'link_url' => 'javascript:alert(1)'])->assertSessionHasErrors('link_url');
        $this->actingAs($user)->post(route('posts.store'), ['body' => 'Promoted to staff engineer!', 'kind' => 'achievement']);

        $this->assertSame(1, EngagementActivity::where('activity_type', 'COMMUNITY_POST')->count());
    }

    public function test_reported_post_removed_from_moderation_queue(): void
    {
        $post = Post::factory()->create(['user_id' => $this->verifiedAlumnus()->user_id]);
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'harassment']);

        $mod = $this->admin(RoleName::CommunityModerator);
        $this->actingAs($mod)->get(route('admin.moderation.index'))->assertInertia(fn (Assert $p) => $p->where('reports.total', 1));
        $this->actingAs($mod)->post(route('admin.moderation.resolve', Report::sole()), ['action' => 'remove', 'resolution' => 'Abusive language.']);

        $this->assertSame(Post::REMOVED, $post->fresh()->status);
    }

    public function test_chapter_admins_host_events_only_for_their_chapter(): void
    {
        $mine = Community::factory()->chapter('Pune')->create();
        $other = Community::factory()->chapter('Delhi')->create();
        $chapterAdmin = $this->admin(RoleName::ChapterAdmin);
        $this->member($mine, $chapterAdmin, 'admin');
        $payload = fn ($cid) => [
            'title' => 'Pune Meetup', 'type' => 'chapter', 'audience' => 'members', 'community_id' => $cid,
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'ends_at' => now()->addWeek()->addHours(2)->format('Y-m-d\TH:i'),
            'is_online' => false, 'venue' => 'Cafe', 'max_guests' => 0,
        ];

        $this->actingAs($chapterAdmin)->post(route('admin.events.store'), $payload($other->id))->assertSessionHasErrors('community_id');
        $this->actingAs($chapterAdmin)->post(route('admin.events.store'), $payload(null))->assertSessionHasErrors('community_id');
        $this->actingAs($chapterAdmin)->post(route('admin.events.store'), $payload($mine->id))->assertRedirect();
        $this->assertSame($mine->id, Event::sole()->community_id);
    }
}
