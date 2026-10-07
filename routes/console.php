<?php

use App\Jobs\DeliverCampaign;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\EventRegistration;
use App\Models\JobPosting;
use App\Notifications\EventReminder;
use App\Services\EventRegistrationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('audit:prune {--days= : Keep this many days (default: security.audit_retention_days)}', function () {
    $days = (int) ($this->option('days') ?: config('security.audit_retention_days'));
    $deleted = AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    $this->info("Pruned {$deleted} audit entries older than {$days} days.");
})->purpose('Delete audit log entries past the retention period');

Artisan::command('events:send-reminders', function () {
    $sent = 0;
    EventRegistration::query()
        ->where('status', EventRegistration::CONFIRMED)
        ->whereNull('reminder_sent_at')
        ->whereHas('event', fn ($q) => $q->published()->whereBetween('starts_at', [now(), now()->addDay()]))
        ->with(['event', 'user'])
        ->chunkById(200, function ($registrations) use (&$sent) {
            foreach ($registrations as $r) {
                $r->user->notify(new EventReminder($r->event));
                $r->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            }
        });
    $this->info("Sent {$sent} event reminders.");
})->purpose('Remind confirmed registrants of events starting within 24 hours');

Artisan::command('jobs:close-expired', function () {
    $closed = JobPosting::where('status', JobPosting::APPROVED)->whereDate('deadline', '<', today())->update(['status' => JobPosting::CLOSED]);
    $this->info("Closed {$closed} expired postings.");
})->purpose('Close job and internship postings past their deadline');

Schedule::command('jobs:close-expired')->dailyAt('00:15')->onOneServer();
Artisan::command('campaigns:dispatch-due', function () {
    Campaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->each(fn ($c) => DeliverCampaign::dispatch($c));
})->purpose('Send scheduled communications whose time has come');

Schedule::command('campaigns:dispatch-due')->everyMinute()->withoutOverlapping()->onOneServer();
Artisan::command('events:release-holds', function () {
    $this->info('Released '.app(EventRegistrationService::class)->releaseExpiredHolds().' unpaid seat holds.');
})->purpose('Cancel unpaid event registrations whose seat hold has expired');

Schedule::command('events:release-holds')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('events:send-reminders')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('audit:prune')->dailyAt('02:30')->onOneServer();
Schedule::command('auth:clear-resets')->hourly();
