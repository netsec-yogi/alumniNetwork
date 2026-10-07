<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AlumniProfile;
use App\Models\EngagementActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Trend, cohort, retention and distribution analytics (SRS 54-55). Aggregates only. */
class AnalyticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::ReportsView->value), 403);

        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $from = $months->first();
        $key = fn ($d) => Carbon::parse($d)->format('Y-m');
        $series = fn ($rows) => $months->map(fn ($m) => ['label' => $m->format('M y'), 'value' => (int) ($rows[$m->format('Y-m')] ?? 0)])->values();

        $registrations = AlumniProfile::where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as m, count(*) as n")->groupBy('m')->pluck('n', 'm');

        $engaged = EngagementActivity::where('activity_date', '>=', $from)
            ->selectRaw("DATE_FORMAT(activity_date, '%Y-%m') as m, count(distinct alumni_profile_id) as n")->groupBy('m')->pluck('n', 'm');

        $byMode = EngagementActivity::where('activity_date', '>=', $from)
            ->selectRaw("engagement_mode, DATE_FORMAT(activity_date, '%Y-%m') as m, count(*) as n")->groupBy('engagement_mode', 'm')->get()
            ->groupBy('engagement_mode')->map(fn ($rows) => $rows->pluck('n', 'm'));

        // Retention: of alumni engaged in the previous 12 months, how many engaged again in the last 12.
        $thisYear = EngagementActivity::where('activity_date', '>=', now()->subYear())->distinct()->pluck('alumni_profile_id');
        $lastYear = EngagementActivity::whereBetween('activity_date', [now()->subYears(2), now()->subYear()])->distinct()->pluck('alumni_profile_id');
        $retained = $lastYear->intersect($thisYear)->count();

        // Cohorts: share of each graduating batch (5-year bands) engaged in the last 12 months.
        $cohorts = DB::table('alumni_profiles as p')
            ->where('p.verification_status', 'verified')->whereNull('p.deleted_at')
            ->leftJoinSub(EngagementActivity::where('activity_date', '>=', now()->subYear())->select('alumni_profile_id')->distinct(), 'e', 'e.alumni_profile_id', '=', 'p.id')
            ->selectRaw('FLOOR(p.graduation_year / 5) * 5 as band, count(*) as total, count(e.alumni_profile_id) as engaged')
            ->groupBy('band')->orderBy('band')->get()
            ->map(fn ($r) => ['label' => "{$r->band}–".($r->band + 4), 'value' => $r->total ? (int) round($r->engaged / $r->total * 100) : 0, 'hint' => "· {$r->engaged}/{$r->total}"]);

        $top = fn (string $col, int $limit = 8) => AlumniProfile::verified()->whereNotNull($col)->where($col, '!=', '')
            ->selectRaw("{$col} as label, count(*) as value")->groupBy($col)->orderByDesc('value')->limit($limit)->get()
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value]);

        return Inertia::render('Admin/Analytics/Index', [
            'registrations' => $series($registrations),
            'engaged' => $series($engaged),
            'modes' => collect(config('engagement.modes'))->map(fn ($label, $mode) => ['mode' => $mode, 'label' => $label, 'series' => $series($byMode[$mode] ?? collect())])->values(),
            'retention' => [
                'previous' => $lastYear->count(),
                'retained' => $retained,
                'rate' => $lastYear->count() ? (int) round($retained / $lastYear->count() * 100) : null,
                'new' => $thisYear->diff($lastYear)->count(),
            ],
            'cohorts' => $cohorts,
            'countries' => $top('country'),
            'cities' => $top('city'),
            'industries' => $top('industry'),
            'companies' => $top('company', 10),
        ]);
    }
}
