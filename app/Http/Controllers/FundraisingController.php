<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Donation;
use App\Models\FundraisingCampaign;
use App\Models\StoredFile;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\AuditLogger;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fundraising campaigns, alumni crowdfunding and Giving Day (SRS 46).
 * Public pages show published campaigns; alumni propose crowdfunding
 * campaigns, which fundraising staff approve before they go live.
 */
class FundraisingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    private function isStaff(?User $user): bool
    {
        return (bool) $user?->can(Permission::FundraisingManage->value);
    }

    public function index(Request $request): Response
    {
        $campaigns = FundraisingCampaign::published()->with('cover')->orderByDesc('starts_at')->get();
        $raised = Donation::where('status', Donation::PAID)->whereIn('fundraising_campaign_id', $campaigns->pluck('id'))
            ->selectRaw('fundraising_campaign_id, sum(amount_paise) as paise, count(distinct donor_email) as donors')
            ->groupBy('fundraising_campaign_id')->get()->keyBy('fundraising_campaign_id');

        return Inertia::render('Fundraising/Index', [
            'campaigns' => $campaigns->map(fn (FundraisingCampaign $c) => $this->card($c, (int) ($raised[$c->id]->paise ?? 0), (int) ($raised[$c->id]->donors ?? 0)))
                ->sortBy(fn ($c) => ['active' => 0, 'upcoming' => 1, 'completed' => 2][$c['stage']] ?? 3)->values(),
            'canPropose' => (bool) $request->user()?->alumniProfile?->isVerified(),
            'mine' => $request->user() ? FundraisingCampaign::where('organizer_id', $request->user()->id)->whereNot('status', 'published')->get(['slug', 'title', 'status', 'rejection_reason']) : [],
        ]);
    }

    public function show(Request $request, FundraisingCampaign $campaign): Response
    {
        $user = $request->user();
        $canManage = $campaign->organizer_id === $user?->id || $this->isStaff($user);
        abort_unless($campaign->status === 'published' || $canManage, 404);
        $campaign->load(['cover', 'organizer:id,name', 'community:id,name,slug', 'updates.author:id,name']);

        $paid = $campaign->donations()->where('status', Donation::PAID);
        $raised = (int) (clone $paid)->sum('amount_paise');

        return Inertia::render('Fundraising/Show', [
            'campaign' => [
                ...$this->card($campaign, $raised, (clone $paid)->distinct()->count('donor_email')),
                'story_html' => $campaign->storyHtml(),
                'organizer' => $campaign->organizer->name,
                'community' => $campaign->community?->only(['name', 'slug']),
                'starts_at' => $campaign->starts_at->format('j M Y, g:i A'),
                'ends_at' => $campaign->ends_at->format('j M Y, g:i A'),
                'ends_at_iso' => $campaign->ends_at->toIso8601String(),
                'status' => $campaign->status,
                'rejection_reason' => $canManage ? $campaign->rejection_reason : null,
            ],
            'recent' => (clone $paid)->where('is_anonymous', false)->latest('paid_at')->limit(10)->get(['donor_name', 'paid_at'])
                ->map(fn ($d) => ['name' => Str::before($d->donor_name, ' '), 'when' => $d->paid_at->diffForHumans()]),
            'leaderboards' => $campaign->type === 'giving_day' ? $this->leaderboards($campaign) : null,
            'updates' => $campaign->updates->map(fn ($u) => ['title' => $u->title, 'body' => $u->body, 'author' => $u->author?->name, 'at' => $u->created_at->format('j M Y')]),
            'canManage' => $canManage,
        ]);
    }

    /** Giving Day competition (SRS 46): participation by graduating batch and by chapter. */
    private function leaderboards(FundraisingCampaign $c): array
    {
        $base = DB::table('donations as d')
            ->join('alumni_profiles as p', 'p.user_id', '=', 'd.user_id')
            ->where('d.fundraising_campaign_id', $c->id)->where('d.status', Donation::PAID);

        $batches = (clone $base)->selectRaw('p.graduation_year as label, count(distinct d.user_id) as donors, sum(d.amount_paise) as paise')
            ->groupBy('p.graduation_year')->orderByDesc('donors')->orderByDesc('paise')->limit(10)->get();

        $chapters = (clone $base)
            ->join('community_members as m', fn ($j) => $j->on('m.user_id', '=', 'd.user_id')->where('m.status', 'active'))
            ->join('communities as g', fn ($j) => $j->on('g.id', '=', 'm.community_id')->where('g.kind', Community::KIND_CHAPTER))
            ->selectRaw('g.name as label, count(distinct d.user_id) as donors, sum(d.amount_paise) as paise')
            ->groupBy('g.name')->orderByDesc('donors')->limit(10)->get();

        $shape = fn ($rows, $prefix = '') => $rows->map(fn ($r) => ['label' => $prefix.$r->label, 'donors' => (int) $r->donors, 'raised' => (int) floor($r->paise / 100)])->values();

        return ['batches' => $shape($batches, 'Batch of '), 'chapters' => $shape($chapters)];
    }

    public function form(Request $request, ?FundraisingCampaign $campaign = null): Response
    {
        $user = $request->user();
        if ($campaign) {
            abort_unless(($campaign->organizer_id === $user->id && in_array($campaign->status, ['draft', 'rejected'], true)) || $this->isStaff($user), 403);
        } else {
            abort_unless($user->alumniProfile?->isVerified() || $this->isStaff($user), 403);
        }

        return Inertia::render('Fundraising/Form', [
            'campaign' => $campaign ? [
                ...$campaign->only(['slug', 'type', 'title', 'summary', 'story', 'category', 'community_id', 'matching_sponsor', 'matching_ratio', 'status']),
                'goal' => $campaign->goal_paise / 100,
                'matching_cap' => $campaign->matching_cap_paise ? $campaign->matching_cap_paise / 100 : null,
                'starts_at' => $campaign->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $campaign->ends_at->format('Y-m-d\TH:i'),
            ] : null,
            'types' => collect(FundraisingCampaign::TYPES)->only($this->isStaff($user) ? array_keys(FundraisingCampaign::TYPES) : ['crowdfunding'])->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'categories' => collect(config('payments.categories'))->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'chapters' => Community::where('kind', 'chapter')->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'isStaff' => $this->isStaff($user),
        ]);
    }

    public function save(Request $request, FileUploadService $uploads, ?FundraisingCampaign $campaign = null): RedirectResponse
    {
        $user = $request->user();
        $staff = $this->isStaff($user);
        if ($campaign) {
            abort_unless(($campaign->organizer_id === $user->id && in_array($campaign->status, ['draft', 'rejected'], true)) || $staff, 403);
        } else {
            abort_unless($user->alumniProfile?->isVerified() || $staff, 403);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in($staff ? array_keys(FundraisingCampaign::TYPES) : ['crowdfunding'])],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['required', 'string', 'max:400'],
            'story' => ['required', 'string', 'min:100', 'max:20000'],
            'category' => ['required', Rule::in(array_keys(config('payments.categories')))],
            'goal' => ['required', 'integer', 'min:10000', 'max:'.($staff ? 100000000 : 2500000)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'community_id' => ['nullable', 'exists:communities,id'],
            'matching_sponsor' => [$staff ? 'nullable' : 'prohibited', 'string', 'max:160'],
            'matching_ratio' => [$staff ? 'nullable' : 'prohibited', 'numeric', 'min:0.1', 'max:5', 'required_with:matching_sponsor'],
            'matching_cap' => [$staff ? 'nullable' : 'prohibited', 'integer', 'min:1000'],
            'cover' => ['nullable', 'file', 'max:'.config('security.uploads.max_image_kb')],
            'publish' => ['boolean'],
        ], ['goal.max' => 'Alumni crowdfunding goals are capped at ₹25,00,000. Contact the fundraising office for larger campaigns.']);

        $campaign ??= (new FundraisingCampaign)->forceFill(['organizer_id' => $user->id, 'status' => 'draft']);
        $campaign->fill([
            ...Arr::except($data, ['goal', 'matching_cap', 'cover', 'publish']),
            'goal_paise' => $data['goal'] * 100,
            'matching_cap_paise' => isset($data['matching_cap']) ? $data['matching_cap'] * 100 : null,
        ]);

        if ($request->hasFile('cover')) {
            try {
                $campaign->cover_file_id = $uploads->storeImage($request->file('cover'), $user, 'campaign_cover', StoredFile::PUBLIC, 1600, 480)->id;
            } catch (UploadRejected $e) {
                throw ValidationException::withMessages(['cover' => $e->getMessage()]);
            }
        }

        // Staff publish directly; alumni campaigns go to review when submitted.
        if (! empty($data['publish'])) {
            $campaign->forceFill($staff
                ? ['status' => 'published', 'approved_by' => $user->id, 'approved_at' => now()]
                : ['status' => 'pending_approval', 'rejection_reason' => null]);
        }
        $campaign->save();
        $this->audit->record('fundraising.saved', 'fundraising', $campaign, null, ['status' => $campaign->status]);

        if ($campaign->status === 'pending_approval') {
            Notification::send(User::permission(Permission::FundraisingManage->value)->get(), new Notice("Campaign awaiting approval: {$campaign->title}", route('admin.fundraising.index', [], false)));
        }

        return redirect()->route('fundraising.show', $campaign)->with('success', match ($campaign->status) {
            'published' => 'Published.',
            'pending_approval' => 'Submitted for approval. The fundraising office will review it.',
            default => 'Draft saved.',
        });
    }

    public function postUpdate(Request $request, FundraisingCampaign $campaign): RedirectResponse
    {
        abort_unless(($campaign->organizer_id === $request->user()->id || $this->isStaff($request->user())) && $campaign->status === 'published', 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:5000']]);

        $update = $campaign->updates()->make($data);
        $update->forceFill(['author_id' => $request->user()->id])->save();

        return back()->with('success', 'Update posted.');
    }

    private function card(FundraisingCampaign $c, int $raised, int $donors): array
    {
        $matched = $c->matchedPaise($raised);

        return [
            'slug' => $c->slug,
            'type' => $c->type,
            'type_label' => FundraisingCampaign::TYPES[$c->type],
            'title' => $c->title,
            'summary' => $c->summary,
            'category' => config('payments.categories')[$c->category] ?? $c->category,
            'stage' => $c->stage(),
            'cover_url' => $c->cover?->url(),
            'goal' => (int) ($c->goal_paise / 100),
            'raised' => (int) floor($raised / 100),
            'matched' => (int) floor($matched / 100),
            'donors' => $donors,
            'percent' => $c->goal_paise ? min(100, (int) floor(($raised + $matched) / $c->goal_paise * 100)) : 0,
            'days_left' => $c->stage() === 'active' ? (int) ceil(now()->diffInHours($c->ends_at) / 24) : null,
            'matching' => $c->matching_sponsor ? ['sponsor' => $c->matching_sponsor, 'ratio' => $c->matching_ratio, 'cap' => $c->matching_cap_paise ? (int) ($c->matching_cap_paise / 100) : null] : null,
        ];
    }
}
