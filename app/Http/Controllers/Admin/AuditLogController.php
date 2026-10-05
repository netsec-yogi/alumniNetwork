<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only audit trail viewer (SRS 80). */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::AuditView->value), 403);

        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:40'],
            'action' => ['nullable', 'string', 'max:60'],
            'user' => ['nullable', 'integer'],
            'request_id' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['module'] ?? null, fn ($q, $v) => $q->where('module', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', addcslashes($v, '%_\\').'%'))
            ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['request_id'] ?? null, fn ($q, $v) => $q->where('request_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<', now()->parse($v)->addDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'at' => $l->created_at->format('d M Y, H:i:s'),
                'user' => $l->user ? ['id' => $l->user->id, 'name' => $l->user->name, 'email' => $l->user->email] : null,
                'action' => $l->action,
                'module' => $l->module,
                'entity' => $l->entity_type ? "{$l->entity_type} #{$l->entity_id}" : null,
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
