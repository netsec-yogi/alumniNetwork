<?php

namespace Tests\Feature\Connect;

use App\Enums\RoleName;
use App\Models\Connection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Services\MessagingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    private function connect($a, $b): void
    {
        Connection::forceCreate(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => 'accepted']);
    }

    public function test_connections_message_freely(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();
        $this->connect($a->user, $b->user);

        $this->actingAs($a->user)->post(route('messages.start', $b), ['body' => 'Hi!'])->assertRedirect();
        $conversation = Conversation::sole();
        $this->assertSame(Conversation::ACTIVE, $conversation->status);

        $this->actingAs($a->user)->post(route('messages.store', $conversation), ['body' => 'Second']);
        $this->actingAs($b->user)->post(route('messages.store', $conversation), ['body' => 'Hello back']);
        $this->assertSame(3, Message::count());

        // Starting again reuses the same conversation.
        $this->actingAs($b->user)->post(route('messages.start', $a), ['body' => 'Again']);
        $this->assertSame(1, Conversation::count());
    }

    public function test_strangers_send_one_request_until_accepted(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();

        $this->actingAs($a->user)->post(route('messages.start', $b), ['body' => 'Hello from your batch!']);
        $conversation = Conversation::sole();
        $this->assertSame(Conversation::REQUEST, $conversation->status);

        $this->actingAs($a->user)->post(route('messages.store', $conversation), ['body' => 'Hello??'])->assertSessionHas('error');
        $this->assertSame(1, Message::count());

        $this->actingAs($b->user)->get(route('messages.index', ['tab' => 'requests']))->assertInertia(fn (Assert $p) => $p->where('conversations.total', 1));

        // Replying accepts.
        $this->actingAs($b->user)->post(route('messages.store', $conversation), ['body' => 'Hi!']);
        $this->assertSame(Conversation::ACTIVE, $conversation->fresh()->status);
    }

    public function test_declined_requests_close_the_conversation(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();
        $this->actingAs($a->user)->post(route('messages.start', $b), ['body' => 'Buy my course']);
        $conversation = Conversation::sole();

        $this->actingAs($a->user)->post(route('messages.respond', $conversation), ['decision' => 'accept'])->assertSessionHas('error');
        $this->actingAs($b->user)->post(route('messages.respond', $conversation), ['decision' => 'decline']);
        $this->actingAs($a->user)->post(route('messages.store', $conversation), ['body' => 'Please?'])->assertSessionHas('error');
    }

    /** SRS 25: messages are not accessible because a user has an administrative role. */
    public function test_nobody_outside_the_conversation_can_read_it_not_even_admins(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();
        $this->connect($a->user, $b->user);
        $this->actingAs($a->user)->post(route('messages.start', $b), ['body' => 'Private']);
        $conversation = Conversation::sole();

        $this->actingAs($this->admin())->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($this->verifiedAlumnus()->user)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('messages.store', $conversation), ['body' => 'intrude'])->assertForbidden();
    }

    public function test_blocks_stop_messages_both_ways(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();
        $this->connect($a->user, $b->user);
        $this->actingAs($a->user)->post(route('messages.start', $b), ['body' => 'Hi']);
        $b->user->blocks()->attach($a->user_id);

        $this->actingAs($a->user)->post(route('messages.store', Conversation::sole()), ['body' => 'Hello?'])->assertSessionHas('error');
        $this->actingAs($b->user)->post(route('messages.store', Conversation::sole()), ['body' => 'x'])->assertSessionHas('error');
    }

    public function test_attachments_are_private_to_participants(): void
    {
        Storage::fake('local');
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();
        $this->connect($a->user, $b->user);
        $pdf = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($pdf, "%PDF-1.4\n%%EOF");

        $this->actingAs($a->user)->post(route('messages.start', $b), ['body' => 'My CV', 'attachment' => new UploadedFile($pdf, 'cv.pdf', null, null, true)]);
        $file = Message::sole()->attachment;

        $this->actingAs($b->user)->get(route('files.show', $file))->assertOk();
        $this->actingAs($this->verifiedAlumnus()->user)->get(route('files.show', $file))->assertForbidden();
        $this->actingAs($this->admin())->get(route('files.show', $file))->assertForbidden();
    }

    public function test_unread_count_and_reporting_a_message(): void
    {
        $a = $this->verifiedAlumnus();
        $b = $this->verifiedAlumnus();
        $this->connect($a->user, $b->user);
        $this->actingAs($a->user)->post(route('messages.start', $b), ['body' => 'Abusive text']);

        $this->assertSame(1, app(MessagingService::class)->unreadCount($b->user));
        $this->actingAs($b->user)->get(route('messages.show', Conversation::sole()));
        $this->assertSame(0, app(MessagingService::class)->unreadCount($b->user));

        $this->actingAs($b->user)->post(route('reports.store'), ['type' => 'message', 'id' => Message::sole()->id, 'reason' => 'harassment'])->assertSessionHas('success');
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('reports.store'), ['type' => 'message', 'id' => Message::sole()->id, 'reason' => 'spam'])->assertForbidden();

        $mod = $this->admin(RoleName::AlumniAdmin);
        $this->actingAs($mod)->get(route('admin.moderation.index'))->assertInertia(fn (Assert $p) => $p->where('reports.data.0.summary', 'Abusive text')->where('reports.data.0.url', null));
        $this->actingAs($mod)->post(route('admin.moderation.resolve', Report::sole()), ['action' => 'remove', 'resolution' => 'Harassment confirmed.']);
        $this->assertSame('removed', Message::sole()->status);
    }

    public function test_pending_alumni_cannot_message(): void
    {
        $pending = $this->verifiedAlumnus(['verification_status' => 'pending']);
        $this->actingAs($pending->user)->post(route('messages.start', $this->verifiedAlumnus()), ['body' => 'hi'])->assertForbidden();
    }
}
