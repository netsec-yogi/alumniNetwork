<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverCampaign;
use App\Models\AlumniProfile;
use App\Models\Campaign;
use App\Models\Community;
use App\Models\Programme;
use App\Services\AudienceBuilder;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Segmented email and in-app communications (SRS 48-49). */
class CampaignController extends Controller
{
    public function __construct(private readonly AudienceBuilder $audiences, private readonly AuditLogger $audit) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permission::CommunicationsSend->value), 403);
    }

    public function index(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Communications/Index', [
            'campaigns' => Campaign::with('creator:id,name')->latest()->paginate(20)->through(fn (Campaign $c) => [
                'id' => $c->id, 'name' => $c->name, 'subject' => $c->subject, 'status' => $c->status, 'channels' => $c->channels,
                'recipients' => $c->recipients_count, 'emails' => $c->emails_count, 'by' => $c->creator?->name,
                'when' => ($c->sent_at ?? $c->scheduled_at)?->format('j M Y, g:i A'),
            ]),
        ]);
    }

    public function edit(Request $request, ?Campaign $campaign = null): Response
    {
        $this->guard($request);
        abort_if($campaign && ! $campaign->isEditable(), 409);

        $distinct = fn (string $col) => AlumniProfile::verified()->whereNotNull($col)->distinct()->orderBy($col)->limit(300)->pluck($col);

        return Inertia::render('Admin/Communications/Form', [
            'campaign' => $campaign ? [...$campaign->only(['id', 'name', 'subject', 'body', 'channels', 'audience', 'status']), 'scheduled_at' => $campaign->scheduled_at?->format('Y-m-d\TH:i')] : null,
            'options' => [
                'roles' => collect([RoleName::Alumni, RoleName::Student, RoleName::Faculty])->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
                'programmes' => Programme::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
                'cities' => $distinct('city'),
                'countries' => $distinct('country'),
                'industries' => $distinct('industry'),
                'interests' => collect(AlumniProfile::INTERESTS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'communities' => Community::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            ],
        ]);
    }

    /** Live audience size while composing. */
    public function preview(Request $request): JsonResponse
    {
        $this->guard($request);

        return response()->json($this->audiences->preview($this->validatedAudience($request->all())));
    }

    public function save(Request $request, ?Campaign $campaign = null): RedirectResponse
    {
        $this->guard($request);
        abort_if($campaign && ! $campaign->isEditable(), 409);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['email', 'in_app'])],
            'audience' => ['required', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'action' => ['required', Rule::in(['draft', 'schedule', 'send'])],
        ]);
        $data['audience'] = $this->validatedAudience($data['audience']);

        $campaign ??= (new Campaign)->forceFill(['created_by' => $request->user()->id]);
        $campaign->fill(collect($data)->except('action')->all());
        $campaign->status = match ($data['action']) {
            'schedule' => $data['scheduled_at'] ? 'scheduled' : 'draft',
            default => 'draft',
        };
        $campaign->save();

        if ($data['action'] === 'send') {
            $this->audit->record('campaign.dispatched', 'communications', $campaign, null, ['audience' => $campaign->audience]);
            DeliverCampaign::dispatch($campaign);

            return redirect()->route('admin.communications.index')->with('success', 'Sending now. Progress shows in the list.');
        }

        return redirect()->route('admin.communications.index')->with('success', $campaign->status === 'scheduled' ? 'Scheduled.' : 'Draft saved.');
    }

    public function cancel(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($request);
        abort_unless($campaign->status === 'scheduled', 409);
        $campaign->forceFill(['status' => 'cancelled'])->save();

        return back()->with('success', 'Cancelled.');
    }

    private function validatedAudience(array $input): array
    {
        $a = validator($input, [
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in([RoleName::Alumni->value, RoleName::Student->value, RoleName::Faculty->value])],
            'programmes' => ['nullable', 'array'], 'programmes.*' => ['integer'],
            'graduation_from' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'graduation_to' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'cities' => ['nullable', 'array'], 'cities.*' => ['string', 'max:100'],
            'countries' => ['nullable', 'array'], 'countries.*' => ['string', 'max:100'],
            'industries' => ['nullable', 'array'], 'industries.*' => ['string', 'max:100'],
            'interests' => ['nullable', 'array'], 'interests.*' => [Rule::in(array_keys(AlumniProfile::INTERESTS))],
            'communities' => ['nullable', 'array'], 'communities.*' => ['integer'],
        ])->validate();

        return array_filter(array_intersect_key($a, array_flip(AudienceBuilder::FILTERS)), fn ($v) => $v !== null && $v !== []);
    }
}
