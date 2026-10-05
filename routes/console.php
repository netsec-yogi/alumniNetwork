<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('audit:prune {--days= : Keep this many days (default: security.audit_retention_days)}', function () {
    $days = (int) ($this->option('days') ?: config('security.audit_retention_days'));
    $deleted = AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    $this->info("Pruned {$deleted} audit entries older than {$days} days.");
})->purpose('Delete audit log entries past the retention period');

Schedule::command('audit:prune')->dailyAt('02:30')->onOneServer();
Schedule::command('auth:clear-resets')->hourly();
