<?php

namespace Tests\Feature\Connect;

use App\Enums\RoleName;
use App\Models\AlumniProfile;
use App\Models\Connection;
use App\Models\EngagementActivity;
use App\Models\Report;
use App\Notifications\ConnectionRequested;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function test_request_accept_flow_notifies_and_records_engagement(): void
    {
        Notification::fake();
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();

        $this->actingAs($a->user)->post(route('connections.store', $b), ['message' => 'Batchmates!'])->assertSessionHas('success');
        Notification::assertSentTo($b->user, ConnectionRequested::class);

        $connection = Connection::firstOrFail();
        // Only the addressee can accept.
        $this->actingAs($a->user)->post(route('connections.accept', $connection))->assertSessionHas('error');
        $this->actingAs($b->user)->post(route('connections.accept', $connection))->assertSessionHas('success');

        $this->assertSame(Connection::ACCEPTED, $connection->fresh()->status);
        $this->assertSame(2, EngagementActivity::where('activity_type', 'CONNECTION_CREATED')->count());
    }

    public function test_duplicate_and_reverse_requests_are_refused(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();

        $this->actingAs($a->user)->post(route('connections.store', $b));
        $this->actingAs($a->user)->post(route('connections.store', $b))->assertSessionHas('error');
        $this->actingAs($b->user)->post(route('connections.store', $a))->assertSessionHas('error');

        $this->assertSame(1, Connection::count());
    }

    public function test_a_third_party_cannot_act_on_someone_elses_connection(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();
        $intruder = $this->verifiedAlumnus();
        $this->actingAs($a->user)->post(route('connections.store', $b));
        $connection = Connection::firstOrFail();

        $this->actingAs($intruder->user)->delete(route('connections.destroy', $connection))->assertSessionHas('error');
        $this->actingAs($intruder->user)->post(route('connections.decline', $connection))->assertSessionHas('error');

        $this->assertModelExists($connection);
    }

    public function test_blocking_removes_connection_and_hides_both_ways(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();
        Connection::forceCreate(['requester_id' => $a->user_id, 'addressee_id' => $b->user_id, 'status' => 'accepted']);

        $this->actingAs($a->user)->post(route('alumni.block', $b))->assertRedirect(route('directory'));

        $this->assertSame(0, Connection::count());
        $this->actingAs($b->user)->get(route('alumni.show', $a))->assertForbidden();
        $this->actingAs($a->user)->get(route('alumni.show', $b))->assertForbidden();
        // Refused with the same generic message as any other refusal, so the
        // blocked member cannot tell they were blocked.
        $this->actingAs($b->user)->post(route('connections.store', $a))->assertSessionHas('error', 'You can’t connect with this member.');

        $this->actingAs($b->user)->get(route('directory'))
            ->assertInertia(fn (Assert $p) => $p->where('profiles.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($a->id)));
    }

    public function test_connections_only_fields_are_visible_to_connections(): void
    {
        $owner = $this->verifiedAlumnus(['company' => 'Quiet Labs', 'visibility' => ['company' => 'connections']]);
        $friend = $this->verifiedAlumnus();
        $stranger = $this->verifiedAlumnus();
        Connection::forceCreate(['requester_id' => $owner->user_id, 'addressee_id' => $friend->user_id, 'status' => 'accepted']);

        $this->actingAs($friend->user)->get(route('alumni.show', $owner))->assertInertia(fn (Assert $p) => $p->where('profile.company', 'Quiet Labs'));
        $this->actingAs($stranger->user)->get(route('alumni.show', $owner))->assertInertia(fn (Assert $p) => $p->missing('profile.company'));

        // ...and the directory filter follows the same rule.
        $this->actingAs($friend->user)->get(route('directory', ['company' => 'Quiet']))->assertInertia(fn (Assert $p) => $p->where('profiles.total', 1));
        $this->actingAs($stranger->user)->get(route('directory', ['company' => 'Quiet']))->assertInertia(fn (Assert $p) => $p->where('profiles.total', 0));
    }

    public function test_pending_alumni_cannot_send_requests(): void
    {
        $pending = $this->verifiedAlumnus(['verification_status' => 'pending']);
        $b = $this->verifiedAlumnus();

        $this->actingAs($pending->user)->post(route('connections.store', $b))->assertForbidden();
    }

    public function test_connection_requests_are_rate_limited(): void
    {
        $a = $this->verifiedAlumnus();
        $targets = AlumniProfile::factory(31)->create();

        foreach ($targets->take(30) as $t) {
            $this->actingAs($a->user)->post(route('connections.store', $t));
        }

        $this->actingAs($a->user)->post(route('connections.store', $targets->last()))->assertStatus(429);
    }

    public function test_reports_are_recorded_once_and_need_visibility(): void
    {
        $reporter = $this->verifiedAlumnus();
        $target = $this->verifiedAlumnus();
        $hidden = $this->verifiedAlumnus(['verification_status' => 'pending']);

        $payload = ['type' => 'alumni_profile', 'id' => $target->id, 'reason' => 'spam'];
        $this->actingAs($reporter->user)->post(route('reports.store'), $payload)->assertSessionHas('success');
        $this->actingAs($reporter->user)->post(route('reports.store'), $payload);
        $this->assertSame(1, Report::count());

        $this->actingAs($reporter->user)->post(route('reports.store'), ['type' => 'alumni_profile', 'id' => $hidden->id, 'reason' => 'spam'])->assertForbidden();
        $this->actingAs($reporter->user)->post(route('reports.store'), ['type' => 'user', 'id' => 1, 'reason' => 'spam'])->assertSessionHasErrors('type');
    }

    public function test_moderation_queue_is_scoped_by_permission(): void
    {
        $target = $this->verifiedAlumnus();
        Report::forceCreate(['reporter_id' => $this->verifiedAlumnus()->user_id, 'reportable_type' => 'alumni_profile', 'reportable_id' => $target->id, 'reason' => 'spam']);

        $usersAdmin = $this->admin(RoleName::AlumniAdmin);
        $this->actingAs($usersAdmin)->get(route('admin.moderation.index'))->assertInertia(fn (Assert $p) => $p->where('reports.total', 1));

        $events = $this->admin(RoleName::EventManager);
        $this->actingAs($events)->get(route('admin.moderation.index'))->assertForbidden();

        $report = Report::firstOrFail();
        $this->actingAs($usersAdmin)->post(route('admin.moderation.resolve', $report), ['action' => 'dismiss', 'resolution' => 'Not spam, just enthusiastic.'])->assertSessionHas('success');
        $this->assertSame(Report::DISMISSED, $report->fresh()->status);
    }

    public function test_notifications_open_marks_read_and_only_follows_local_paths(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $user->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'test', 'data' => ['title' => 'Hi', 'url' => '/connections']]);
        $evil = $user->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'test', 'data' => ['title' => 'Hi', 'url' => '//evil.example.com']]);

        $first = $user->notifications()->where('data->url', '/connections')->first();
        $this->actingAs($user)->post(route('notifications.open', $first->id))->assertRedirect('/connections');
        $this->assertNotNull($first->fresh()->read_at);

        $this->actingAs($user)->post(route('notifications.open', $evil->id))->assertRedirect(route('notifications.index'));

        $other = $this->verifiedAlumnus()->user;
        $this->actingAs($other)->post(route('notifications.open', $first->id))->assertNotFound();
    }

    public function test_students_can_connect_with_alumni(): void
    {
        $student = $this->user(RoleName::Student);
        $alumnus = $this->verifiedAlumnus();

        $this->actingAs($student)->post(route('connections.store', $alumnus))->assertSessionHas('success');
    }
}
