<?php

use App\Http\Controllers\Admin\AlumniController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CommunityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\ProgrammeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use Illuminate\Support\Facades\Route;

/*
 | Prefixed /admin, named admin.*, and behind auth + verified email +
 | the access-admin gate (bootstrap/app.php). Mandatory 2FA is enforced by
 | EnsureTwoFactorEnrolled before any of these run. Each controller action
 | still authorises itself against its policy.
 */

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::post('/users', [UserController::class, 'store'])->middleware('password.confirm')->name('users.store');
Route::put('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
Route::post('/users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->middleware('password.confirm')->name('users.reset-two-factor');
// SRS 77: privilege changes need a recent password confirmation.
Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->middleware('password.confirm')->name('users.roles');

Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');
Route::post('/verification/{verificationRequest}/approve', [VerificationController::class, 'approve'])->name('verification.approve');
Route::post('/verification/{verificationRequest}/reject', [VerificationController::class, 'reject'])->name('verification.reject');

Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
Route::post('/moderation/{report}/resolve', [ModerationController::class, 'resolve'])->name('moderation.resolve');

// Events (SRS 35-37). Admin routes address events by slug, like public ones.
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
Route::post('/events', [EventController::class, 'store'])->name('events.store');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
Route::post('/events/{event}/publish', [EventController::class, 'publish'])->name('events.publish');
Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
Route::get('/events/{event}/export', [EventController::class, 'export'])->name('events.export');
Route::get('/events/{event}/check-in', [EventController::class, 'checkInPage'])->name('events.check-in');
Route::post('/events/{event}/check-in', [EventController::class, 'checkIn'])->name('events.check-in.store');

// Job moderation (SRS 32)
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::post('/jobs/{job}/approve', [JobController::class, 'approve'])->name('jobs.approve');
Route::post('/jobs/{job}/reject', [JobController::class, 'reject'])->name('jobs.reject');

// Communities and chapters (SRS 27-28)
Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('communities.destroy');

// Alumni CRM, import and export (SRS 51, 94-95)
Route::get('/alumni', [AlumniController::class, 'index'])->name('alumni.index');
Route::get('/alumni/export', [AlumniController::class, 'export'])->middleware(['password.confirm', 'throttle:sensitive'])->name('alumni.export');
Route::get('/alumni/import', [AlumniController::class, 'importPage'])->name('alumni.import');
Route::post('/alumni/import', [AlumniController::class, 'importPreview'])->middleware('throttle:sensitive')->name('alumni.import.preview');
Route::post('/alumni/import/{import}/confirm', [AlumniController::class, 'importConfirm'])->name('alumni.import.confirm');
Route::get('/alumni/{alumnus}', [AlumniController::class, 'show'])->whereNumber('alumnus')->name('alumni.show');

// Programmes and departments
Route::get('/programmes', [ProgrammeController::class, 'index'])->name('programmes.index');
Route::post('/programmes', [ProgrammeController::class, 'save'])->name('programmes.store');
Route::put('/programmes/{programme}', [ProgrammeController::class, 'save'])->name('programmes.update');
Route::post('/departments', [ProgrammeController::class, 'storeDepartment'])->name('departments.store');

// Reports (SRS 54-55)
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/engagement.csv', [ReportController::class, 'export'])->middleware('password.confirm')->name('reports.export');
