<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\FundraisingCampaign;
use App\Notifications\Notice;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Campaign approval and oversight (SRS 46). */
class FundraisingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permission::FundraisingManage->value), 403);
    }

    public function index(Request $request): Response
    {
        $this->guard($request);
        $raised = Donation::where('status', 'paid')->whereNotNull('fundraising_campaign_id')
            ->selectRaw('fundraising_campaign_id, sum(amount_paise) as paise')->groupBy('fundraising_campaign_id')->pluck('paise', 'fundraising_campaign_id');

        return Inertia::render('Admin/Fundraising/Index', [
            'campaigns' => FundraisingCampaign::with('organizer:id,name,email')->orderByRaw("FIELD(status, 'pending_approval', 'published', 'draft', 'rejected', 'cancelled')")->latest()->get()
                ->map(fn (FundraisingCampaign $c) => [
                    'id' => $c->id, 'slug' => $c->slug, 'title' => $c->title, 'type' => FundraisingCampaign::TYPES[$c->type],
                    'status' => $c->status, 'stage' => $c->stage(), 'organizer' => $c->organizer->name, 'organizer_email' => $c->organizer->email,
                    'goal' => (int) ($c->goal_paise / 100), 'raised' => (int) floor(($raised[$c->id] ?? 0) / 100),
                    'dates' => $c->starts_at->format('j M').' – '.$c->ends_at->format('j M Y'),
                ]),
        ]);
    }

    public function review(Request $request, FundraisingCampaign $campaign): RedirectResponse
    {
        $this->guard($request);
        abort_unless($campaign->status === 'pending_approval', 409);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:500']]);

        $campaign->forceFill($data['decision'] === 'approve'
            ? ['status' => 'published', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'rejection_reason' => null]
            : ['status' => 'rejected', 'rejection_reason' => $data['reason']])->save();

        $this->audit->record("fundraising.{$campaign->status}", 'fundraising', $campaign);
        $campaign->organizer->notify(new Notice(
            $campaign->status === 'published' ? "Your campaign is live: {$campaign->title}" : "Your campaign needs changes: {$campaign->title}",
            route('fundraising.show', $campaign, false),
            $campaign->rejection_reason,
            true,
        ));

        return back()->with('success', $campaign->status === 'published' ? 'Approved and published.' : 'Sent back with your notes.');
    }

    public function cancel(Request $request, FundraisingCampaign $campaign): RedirectResponse
    {
        $this->guard($request);
        $campaign->forceFill(['status' => 'cancelled'])->save();
        $this->audit->record('fundraising.cancelled', 'fundraising', $campaign);

        return back()->with('success', 'Campaign cancelled. Donations already made remain receipted.');
    }
}
