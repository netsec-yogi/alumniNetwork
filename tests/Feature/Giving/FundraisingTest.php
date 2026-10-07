<?php

namespace Tests\Feature\Giving;

use App\Enums\RoleName;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Donation;
use App\Models\FundraisingCampaign;
use App\Services\DonationService;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FundraisingTest extends TestCase
{
    private function payload(array $o = []): array
    {
        return array_merge([
            'type' => 'crowdfunding', 'title' => 'Batch of 2012 Scholarship', 'summary' => 'Fees for two students a year.',
            'story' => str_repeat('Our batch wants to give back to students who need it most. ', 3), 'category' => 'scholarship',
            'goal' => 500000, 'starts_at' => now()->subHour()->format('Y-m-d\TH:i'), 'ends_at' => now()->addMonth()->format('Y-m-d\TH:i'), 'publish' => true,
        ], $o);
    }

    private function campaign(array $o = []): FundraisingCampaign
    {
        $c = new FundraisingCampaign(array_merge(['type' => 'institutional', 'title' => 'New Lab', 'summary' => 'x', 'story' => 'x', 'category' => 'research', 'goal_paise' => 10000000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek()], $o));
        $c->forceFill(['status' => 'published', 'organizer_id' => $this->admin(RoleName::FundraisingManager)->id])->save();

        return $c;
    }

    public function test_alumni_crowdfunding_needs_approval_before_going_live(): void
    {
        $alumnus = $this->verifiedAlumnus()->user;
        $this->actingAs($alumnus)->post(route('fundraising.store'), $this->payload())->assertRedirect();
        $c = FundraisingCampaign::sole();
        $this->assertSame('pending_approval', $c->status);

        $this->actingAsGuest();
        $this->get(route('fundraising.show', $c))->assertNotFound();
        $this->get(route('fundraising.index'))->assertInertia(fn (Assert $p) => $p->where('campaigns', []));

        $this->actingAs($this->admin(RoleName::FundraisingManager))->post(route('admin.fundraising.review', $c), ['decision' => 'approve'])->assertSessionHas('success');
        $this->actingAsGuest();
        $this->get(route('fundraising.show', $c))->assertOk()->assertInertia(fn (Assert $p) => $p->where('campaign.stage', 'active'));
    }

    public function test_alumni_cannot_set_matching_or_exceed_goal_cap_or_pick_type(): void
    {
        $alumnus = $this->verifiedAlumnus()->user;

        $this->actingAs($alumnus)->post(route('fundraising.store'), $this->payload(['matching_sponsor' => 'Me', 'matching_ratio' => 5]))->assertSessionHasErrors(['matching_sponsor', 'matching_ratio']);
        $this->actingAs($alumnus)->post(route('fundraising.store'), $this->payload(['goal' => 90000000]))->assertSessionHasErrors('goal');
        $this->actingAs($alumnus)->post(route('fundraising.store'), $this->payload(['type' => 'giving_day']))->assertSessionHasErrors('type');
        $this->actingAs($this->user(RoleName::Student))->post(route('fundraising.store'), $this->payload())->assertForbidden();
    }

    public function test_campaign_donations_count_matching_and_campaign_sets_category(): void
    {
        Mail::fake();
        $c = $this->campaign(['matching_sponsor' => 'Acme Corp', 'matching_ratio' => 1, 'matching_cap_paise' => 300000]);

        $this->post(route('giving.store'), ['campaign' => $c->slug, 'category' => 'general', 'amount' => 5000, 'donor_name' => 'Asha', 'donor_email' => 'a@example.com', 'indian_resident' => true]);
        $d = Donation::sole();
        $this->assertSame(['research', $c->id], [$d->category, $d->fundraising_campaign_id]);
        app(DonationService::class)->markPaid($d, 'pay_1');

        $this->get(route('fundraising.show', $c))->assertInertia(fn (Assert $p) => $p
            ->where('campaign.raised', 5000)
            ->where('campaign.matched', 3000) // capped
            ->where('campaign.donors', 1));
    }

    public function test_ended_or_unpublished_campaigns_refuse_donations(): void
    {
        $ended = $this->campaign(['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
        $this->post(route('giving.store'), ['campaign' => $ended->slug, 'category' => 'general', 'amount' => 500, 'donor_name' => 'A', 'donor_email' => 'a@example.com', 'indian_resident' => true])
            ->assertSessionHas('error');
        $this->assertSame(0, Donation::count());
        $this->assertSame('completed', $ended->stage());
    }

    public function test_giving_day_leaderboards_by_batch_and_chapter(): void
    {
        Mail::fake();
        $c = $this->campaign(['type' => 'giving_day']);
        $chapter = Community::factory()->chapter('Pune')->create();
        $service = app(DonationService::class);

        foreach ([2012, 2012, 2015] as $year) {
            $u = $this->verifiedAlumnus(['graduation_year' => $year])->user;
            CommunityMember::forceCreate(['community_id' => $chapter->id, 'user_id' => $u->id, 'role' => 'member', 'status' => 'active']);
            $d = $service->start(['category' => 'research', 'amount' => 1000, 'donor_name' => $u->name, 'donor_email' => $u->email, 'fundraising_campaign_id' => $c->id], $u, null)['donation'];
            $service->markPaid($d, 'p'.$u->id);
        }

        $this->get(route('fundraising.show', $c))->assertInertia(fn (Assert $p) => $p
            ->where('leaderboards.batches.0.label', 'Batch of 2012')
            ->where('leaderboards.batches.0.donors', 2)
            ->where('leaderboards.chapters.0.donors', 3));
    }

    public function test_only_organiser_or_staff_post_updates(): void
    {
        $c = $this->campaign();
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('fundraising.updates.store', $c), ['title' => 'x', 'body' => 'y'])->assertForbidden();
        $this->actingAs($c->organizer)->post(route('fundraising.updates.store', $c), ['title' => 'Halfway!', 'body' => 'Thank you all.'])->assertSessionHas('success');
    }
}
