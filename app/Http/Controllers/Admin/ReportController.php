<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AlumniProfile;
use App\Models\Community;
use App\Models\Donation;
use App\Models\EngagementActivity;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\JobPosting;
use App\Models\MentorshipRequest;
use App\Models\Post;
use App\Models\SpeakerInvitation;
use App\Models\VolunteerSignup;
use App\Services\AuditLogger;
use App\Services\EngagementScore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Engagement analytics and reports (SRS 54-55). CASE reporting counts
 * engaged alumni per mode from the activity rows; the aggregate score is
 * shown alongside, never instead.
 */
class ReportController extends Controller
{
    public function __construct(private readonly EngagementScore $score) {}

    /** @return array{0: Carbon, 1: Carbon} */
    private function window(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return [
            isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subYear()->startOfDay(),
            isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay(),
        ];
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::ReportsView->value), 403);
        [$from, $to] = $this->window($request);
        $activities = fn () => $this->score->window(EngagementActivity::query(), $from, $to);
        $verified = AlumniProfile::verified()->count();

        $byMode = $activities()->selectRaw('engagement_mode, count(*) as activities, count(distinct alumni_profile_id) as alumni')
            ->groupBy('engagement_mode')->get()->keyBy('engagement_mode');

        $engaged = $activities()->distinct()->count('alumni_profile_id');

        $registrations = EventRegistration::whereHas('event', fn ($q) => $q->whereBetween('starts_at', [$from, $to]));

        return Inertia::render('Admin/Reports/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'alumni' => [
                'verified' => $verified,
                'new' => AlumniProfile::whereBetween('created_at', [$from, $to])->count(),
                'engaged' => $engaged,
                'engagement_rate' => $verified ? round($engaged / $verified * 100, 1) : 0,
            ],
            'case' => collect(config('engagement.modes'))->map(fn ($label, $mode) => [
                'mode' => $mode,
                'label' => $label,
                'alumni' => (int) ($byMode[$mode]->alumni ?? 0),
                'activities' => (int) ($byMode[$mode]->activities ?? 0),
                'rate' => $verified ? round(($byMode[$mode]->alumni ?? 0) / $verified * 100, 1) : 0,
            ])->values(),
            'byType' => $activities()->selectRaw('activity_type, count(*) as n')->groupBy('activity_type')->orderByDesc('n')->pluck('n', 'activity_type'),
            'events' => [
                'held' => Event::published()->whereBetween('starts_at', [$from, $to])->count(),
                'registrations' => (clone $registrations)->where('status', EventRegistration::CONFIRMED)->count(),
                'attended' => (clone $registrations)->whereNotNull('checked_in_at')->count(),
            ],
            'career' => [
                'jobs' => JobPosting::where('type', 'job')->whereIn('status', ['approved', 'closed'])->whereBetween('created_at', [$from, $to])->count(),
                'internships' => JobPosting::where('type', 'internship')->whereIn('status', ['approved', 'closed'])->whereBetween('created_at', [$from, $to])->count(),
                'referrals' => EngagementActivity::where('activity_type', 'JOB_REFERRAL')->whereBetween('activity_date', [$from, $to])->count(),
            ],
            'mentoring' => [
                'requests' => MentorshipRequest::whereBetween('created_at', [$from, $to])->count(),
                'accepted' => MentorshipRequest::whereIn('status', ['accepted', 'completed'])->whereBetween('created_at', [$from, $to])->count(),
                'completed' => MentorshipRequest::where('status', 'completed')->whereBetween('completed_at', [$from, $to])->count(),
            ],
            'giving' => [
                'raised' => (int) floor(Donation::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum('amount_paise') / 100),
                'gifts' => Donation::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->count(),
                'donors' => Donation::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->distinct()->count('donor_email'),
            ],
            'volunteering' => [
                'hours' => (float) VolunteerSignup::where('status', 'completed')->whereBetween('approved_at', [$from, $to])->sum('hours'),
                'volunteers' => VolunteerSignup::where('status', 'completed')->whereBetween('approved_at', [$from, $to])->distinct()->count('user_id'),
                'talks' => SpeakerInvitation::where('status', 'delivered')->whereBetween('updated_at', [$from, $to])->count(),
            ],
            'community' => [
                'communities' => Community::where('kind', 'community')->count(),
                'chapters' => Community::where('kind', 'chapter')->count(),
                'posts' => Post::whereBetween('created_at', [$from, $to])->count(),
            ],
            'leaders' => $this->score->leaders(10, $from, $to)->map(fn ($row) => [
                'id' => $row->alumni_profile_id,
                'name' => $row->profile?->user?->name,
                'programme' => $row->profile?->programme?->code,
                'score' => (int) $row->score,
                'activities' => (int) $row->activities,
            ]),
            'weights' => config('engagement.weights'),
            'canExport' => $request->user()->can(Permission::ReportsExport->value),
        ]);
    }

    /** Per-alumnus engagement summary as CSV, for CASE returns (SRS 55). */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->can(Permission::ReportsExport->value), 403);
        [$from, $to] = $this->window($request);
        $audit->record('reports.engagement_exported', 'reports', null, null, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        $points = $this->score->pointsExpression()->getValue(DB::connection()->getQueryGrammar());
        $modes = array_keys(config('engagement.modes'));

        return response()->streamDownload(function () use ($from, $to, $points, $modes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Profile ID', 'Name', 'Programme', 'Graduation year', 'Score', ...array_map('ucfirst', $modes), 'Activities']);

            $this->score->window(EngagementActivity::query(), $from, $to)
                ->select('alumni_profile_id', DB::raw("{$points} as score"), DB::raw('count(*) as activities'),
                    ...array_map(fn ($m) => DB::raw("SUM(engagement_mode = '{$m}') as {$m}"), $modes))
                ->groupBy('alumni_profile_id')->orderByDesc('score')
                ->with('profile.user:id,name', 'profile.programme:id,code')
                ->chunk(500, function ($rows) use ($out, $modes) {
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $r->alumni_profile_id, $r->profile?->user?->name, $r->profile?->programme?->code, $r->profile?->graduation_year,
                            (int) $r->score, ...array_map(fn ($m) => (int) $r->{$m}, $modes), (int) $r->activities,
                        ]);
                    }
                });
            fclose($out);
        }, "engagement-{$from->toDateString()}-to-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv']);
    }
}
