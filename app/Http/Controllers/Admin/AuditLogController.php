<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AdminPasswordService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only audit trail viewer (SRS 80). */
class AuditLogController extends Controller
{
    /** Friendly names for the actions the security team looks for most. */
    private const LABELS = [
        AdminPasswordService::CHANGED => 'Password changed by administrator',
        AdminPasswordService::RESET => 'Password reset by administrator',
        'password.changed' => 'Password changed by user',
        'password.reset' => 'Password reset by user (email link)',
        'otp_login.requested' => 'Email OTP requested',
        'otp_login.resend' => 'Email OTP resend requested',
        'otp_login.sent' => 'Email OTP sent',
        'otp_login.send_failed' => 'Email OTP could not be sent',
        'otp_login.verified' => 'Signed in with email OTP',
        'otp_login.failed' => 'Incorrect email OTP',
        'otp_login.expired' => 'Email OTP expired',
        'otp_login.rate_limited' => 'Email OTP rate-limited',
        'otp_login.blocked' => 'Email OTP refused (account not eligible)',
        'branding.draft_saved' => 'Branding draft changed',
        'branding.published' => 'Branding published',
        'branding.unpublished' => 'Branding unpublished',
        'branding.restored' => 'Branding draft restored',
        'landing_content.draft_saved' => 'Landing page text draft changed',
        'landing_content.published' => 'Landing page text published',
        'landing_content.unpublished' => 'Landing page text unpublished',
        'landing_content.restored' => 'Landing page text draft restored',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::AuditView->value), 403);

        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:40'],
            'action' => ['nullable', 'string', 'max:60'],
            'user' => ['nullable', 'integer'],
            'request_id' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'in:success,failed,denied'],
            'preset' => ['nullable', 'in:passwords'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['module'] ?? null, fn ($q, $v) => $q->where('module', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', addcslashes($v, '%_\\').'%'))
            ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['request_id'] ?? null, fn ($q, $v) => $q->where('request_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when(($filters['preset'] ?? null) === 'passwords', fn ($q) => $q->whereIn('action', [AdminPasswordService::CHANGED, AdminPasswordService::RESET, 'password.changed', 'password.reset']))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<', now()->parse($v)->addDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        // Affected users' names for the page, in one query.
        $subjects = User::withTrashed()->whereIn('id', $logs->getCollection()->where('entity_type', 'User')->pluck('entity_id'))->pluck('name', 'id');

        $logs->through(fn (AuditLog $l) => [
            'id' => $l->id,
            'at' => $l->created_at->format('d M Y, H:i:s'),
            'user' => $l->user ? ['id' => $l->user->id, 'name' => $l->user->name, 'email' => $l->user->email] : null,
            'action' => $l->action,
            'module' => $l->module,
            'entity' => $l->entity_type ? "{$l->entity_type} #{$l->entity_id}" : null,
            'subject' => $l->entity_type === 'User' ? ($subjects[$l->entity_id] ?? "User #{$l->entity_id}") : null,
            'label' => self::LABELS[$l->action] ?? null,
            'status' => $l->status,
            'reason' => $l->reason,
            'old_values' => $l->old_values,
            'new_values' => $l->new_values,
            'ip' => $l->ip_address,
            'user_agent' => $l->user_agent,
            'request_id' => $l->request_id,
        ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => (object) $filters,
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
        ]);
    }
}
