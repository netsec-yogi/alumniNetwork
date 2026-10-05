<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AlumniProfile;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
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

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalAlumni' => $byStatus->sum(),
                'verified' => (int) ($byStatus[VerificationStatus::Verified->value] ?? 0),
                'pending' => (int) ($byStatus[VerificationStatus::Pending->value] ?? 0),
                'rejected' => (int) ($byStatus[VerificationStatus::Rejected->value] ?? 0),
                'newThisMonth' => AlumniProfile::where('created_at', '>=', now()->startOfMonth())->count(),
                'activeUsers30d' => User::where('last_login_at', '>=', now()->subDays(30))->count(),
            ],
            'byProgramme' => $byProgramme,
            'security' => $security,
        ]);
    }
}
