<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AlumniProfile;
use App\Models\AuditLog;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\FundraisingCampaign;
use App\Models\JobPosting;
use App\Models\MentorProfile;
use App\Models\MentorshipRequest;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Admin KPIs (SRS 56) and, for security staff, the security panel (SRS 57). */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $since = now()->subDay();

        $byStatus = AlumniProfile::query()
            ->select('verification_status', DB::raw('count(*) as total'))
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status');

        $byProgramme = AlumniProfile::verified()
            ->join('programmes', 'programmes.id', '=', 'alumni_profiles.programme_id')
            ->select('programmes.name', DB::raw('count(*) as total'))
            ->groupBy('programmes.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $security = null;
        if ($user->can(Permission::SecurityManage->value) || $user->can(Permission::AuditView->value)) {
            $security = [
                'failedLogins' => AuditLog::where('action', 'login.failed')->where('created_at', '>=', $since)->count(),
                'rateLimited' => AuditLog::where('action', 'login.rate_limited')->where('created_at', '>=', $since)->count(),
                'lockedAccounts' => User::where('locked_until', '>', now())->count(),
                'roleChanges' => AuditLog::whereIn('action', ['role.assigned', 'role.removed'])->where('created_at', '>=', now()->subWeek())->count(),
                'adminsWithout2fa' => User::role(config('security.two_factor_required_roles'))->whereNull('two_factor_confirmed_at')->count(),
                'recent' => AuditLog::with('user:id,name')
                    ->where('module', 'security')
                    ->latest('id')
                    ->limit(8)
                    ->get(['id', 'user_id', 'action', 'ip_address', 'created_at'])
                    ->map(fn (AuditLog $l) => [
                        'id' => $l->id,
                        'action' => $l->action,
                        'user' => $l->user?->name,
                        'ip' => $l->ip_address,
                        'at' => $l->created_at->diffForHumans(),
                    ]),
            ];
        }

        // 12-month sign-up trend and month-on-month change, for the overview chart.
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $signups = AlumniProfile::where('created_at', '>=', $months->first())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as m, count(*) as n")->groupBy('m')->pluck('n', 'm');
        $trend = $months->map(fn ($m) => ['label' => $m->format('M'), 'value' => (int) ($signups[$m->format('Y-m')] ?? 0)])->values();
        $lastMonth = AlumniProfile::whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])->count();
        $thisMonth = AlumniProfile::where('created_at', '>=', now()->startOfMonth())->count();

        // Work queues, only those this admin can act on.
        $attention = collect([
            ['label' => 'Alumni awaiting verification', 'count' => (int) ($byStatus[VerificationStatus::Pending->value] ?? 0), 'route' => 'admin.verification.index', 'show' => $user->can(Permission::AlumniVerify->value)],
            ['label' => 'Jobs awaiting review', 'count' => JobPosting::where('status', 'pending')->count(), 'route' => 'admin.jobs.index', 'show' => $user->can(Permission::JobsModerate->value)],
            ['label' => 'Open abuse reports', 'count' => Report::where('status', 'open')->count(), 'route' => 'admin.moderation.index', 'show' => $user->canAny([Permission::CommunitiesModerate->value, Permission::JobsModerate->value, Permission::UsersManage->value])],
            ['label' => 'Campaigns awaiting approval', 'count' => FundraisingCampaign::where('status', 'pending_approval')->count(), 'route' => 'admin.fundraising.index', 'show' => $user->can(Permission::FundraisingManage->value)],
            ['label' => 'Achievements to review', 'count' => Achievement::where('status', 'submitted')->count(), 'route' => 'admin.achievements.index', 'show' => $user->can(Permission::ContentManage->value)],
        ])->filter(fn ($a) => $a['show'])->map(fn ($a) => Arr::except($a, 'show'))->values();

        return Inertia::render('Admin/Dashboard', [
            'trend' => $trend,
            'growth' => $lastMonth ? (int) round(($thisMonth - $lastMonth) / $lastMonth * 100) : null,
            'attention' => $attention,
            'stats' => [
                'totalAlumni' => $byStatus->sum(),
                'verified' => (int) ($byStatus[VerificationStatus::Verified->value] ?? 0),
                'pending' => (int) ($byStatus[VerificationStatus::Pending->value] ?? 0),
                'rejected' => (int) ($byStatus[VerificationStatus::Rejected->value] ?? 0),
                'newThisMonth' => AlumniProfile::where('created_at', '>=', now()->startOfMonth())->count(),
                'activeUsers30d' => User::where('last_login_at', '>=', now()->subDays(30))->count(),
            ],
            'byProgramme' => $byProgramme,
            'modules' => [
                'upcomingEvents' => Event::published()->upcoming()->count(),
                'registrations' => EventRegistration::where('status', 'confirmed')->whereHas('event', fn ($q) => $q->upcoming())->count(),
                'liveJobs' => JobPosting::live()->count(),
                'pendingJobs' => JobPosting::where('status', 'pending')->count(),
                'mentors' => MentorProfile::where('is_accepting', true)->count(),
                'activeMentorships' => MentorshipRequest::where('status', 'accepted')->count(),
                'communities' => Community::count(),
                'openReports' => Report::where('status', 'open')->count(),
            ],
            'security' => $security,
        ]);
    }
}
