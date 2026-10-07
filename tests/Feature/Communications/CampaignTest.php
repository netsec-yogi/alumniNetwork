<?php

namespace Tests\Feature\Communications;

use App\Enums\RoleName;
use App\Http\Controllers\CommunicationPreferenceController;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\Consent;
use App\Models\Programme;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\AudienceBuilder;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    private function optIn(User $u, bool $granted = true): void
    {
        $u->consents()->create(['consent_type' => Consent::COMMUNICATIONS, 'version' => 'test', 'granted' => $granted]);
    }

    public function test_audience_segmentation_matches_srs_example(): void
    {
        $cse = Programme::where('code', 'BTECH-CSE')->value('id');
        $match = $this->verifiedAlumnus(['programme_id' => $cse, 'graduation_year' => 2014, 'city' => 'Bengaluru']);
        $this->verifiedAlumnus(['programme_id' => $cse, 'graduation_year' => 2020, 'city' => 'Bengaluru']); // wrong batch
        $this->verifiedAlumnus(['programme_id' => $cse, 'graduation_year' => 2014, 'city' => 'Pune']);     // wrong city
        $this->verifiedAlumnus(['programme_id' => $cse, 'graduation_year' => 2014, 'city' => 'Bengaluru', 'verification_status' => 'pending']); // unverified

        $ids = app(AudienceBuilder::class)->query(['programmes' => [$cse], 'graduation_from' => 2010, 'graduation_to' => 2018, 'cities' => ['Bengaluru']])->pluck('id');

        $this->assertEquals([$match->user_id], $ids->all());
    }

    public function test_email_only_reaches_people_whose_latest_consent_is_granted(): void
    {
        $yes = $this->verifiedAlumnus()->user;
        $changedMind = $this->verifiedAlumnus()->user;
        $never = $this->verifiedAlumnus()->user;
        $this->optIn($yes);
        $this->optIn($changedMind);
        $this->optIn($changedMind, false);

        $emailable = app(AudienceBuilder::class)->emailable(app(AudienceBuilder::class)->query([]))->pluck('users.id');

        $this->assertEquals([$yes->id], $emailable->all());
        $this->assertNotContains($never->id, $emailable);
    }

    public function test_sending_delivers_in_app_to_all_and_email_to_opted_in(): void
    {
        Mail::fake();
        Notification::fake();
        $admin = $this->admin(RoleName::AlumniAdmin);
        $a = $this->verifiedAlumnus()->user;
        $b = $this->verifiedAlumnus()->user;
        $this->optIn($a);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.communications.store'), [
            'name' => 'Reunion 2026', 'subject' => 'Save the date', 'body' => "Join us!\n\n<script>alert(1)</script>",
            'channels' => ['email', 'in_app'], 'audience' => ['roles' => ['alumni']], 'action' => 'send',
        ])->assertRedirect(route('admin.communications.index'));

        $campaign = Campaign::sole();
        $this->assertSame(['sent', 2, 1], [$campaign->status, $campaign->recipients_count, $campaign->emails_count]);
        Notification::assertSentTo([$a, $b], Notice::class);
        Mail::assertQueued(CampaignMail::class, fn ($m) => $m->recipient->is($a));
        Mail::assertNotQueued(CampaignMail::class, fn ($m) => $m->recipient->is($b));
        $this->assertStringNotContainsString('<script', $campaign->bodyHtml());
        $this->assertDatabaseHas('audit_logs', ['action' => 'campaign.sent', 'entity_id' => $campaign->id]);

        // Delivery is idempotent.
        app(CampaignService::class)->deliver($campaign);
        $this->assertSame(2, \DB::table('campaign_recipients')->count());
    }

    public function test_sending_needs_permission_and_password_confirmation(): void
    {
        $payload = ['name' => 'x', 'subject' => 'x', 'body' => 'x', 'channels' => ['in_app'], 'audience' => [], 'action' => 'send'];

        $this->actingAs($this->admin(RoleName::AlumniAdmin))->post(route('admin.communications.store'), $payload)->assertRedirect(route('password.confirm'));
        $this->actingAs($this->admin(RoleName::EventManager))->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.communications.store'), $payload)->assertForbidden();
    }

    public function test_unsubscribe_requires_signature_and_get_does_not_change_state(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $this->optIn($user);
        $url = URL::signedRoute('unsubscribe.show', ['user' => $user->id]);

        $this->get(route('unsubscribe.show', ['user' => $user->id]))->assertForbidden(); // unsigned
        $this->get($url)->assertOk();
        $this->assertTrue(CommunicationPreferenceController::current($user), 'GET must not unsubscribe');

        // One-click POST from a mail client: no session, no CSRF token.
        $this->post(str_replace('/unsubscribe/', '/unsubscribe/', $url))->assertOk();
        $this->assertFalse(CommunicationPreferenceController::current($user));

        $other = $this->verifiedAlumnus()->user;
        $tampered = str_replace("/unsubscribe/{$user->id}", "/unsubscribe/{$other->id}", $url);
        $this->post($tampered)->assertForbidden();
    }

    public function test_scheduled_campaigns_go_out_when_due(): void
    {
        Notification::fake();
        $this->verifiedAlumnus();
        $c = new Campaign(['name' => 'x', 'subject' => 'Hello', 'body' => 'x', 'channels' => ['in_app'], 'audience' => []]);
        $c->forceFill(['status' => 'scheduled', 'scheduled_at' => now()->subMinute()])->save();

        $this->artisan('campaigns:dispatch-due')->assertSuccessful();

        $this->assertSame('sent', $c->fresh()->status);
    }
}
