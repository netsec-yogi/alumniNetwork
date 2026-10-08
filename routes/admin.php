<?php

use App\Http\Controllers\Admin\AlumniController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CommunityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DonationController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\FundraisingController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\LandingController;
use App\Http\Controllers\Admin\MediaGalleryController;
use App\Http\Controllers\Admin\MediaSettingsController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\ProgrammeController;
use App\Http\Controllers\Admin\RecognitionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SurveyController;
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
Route::put('/users/{user}/email', [UserController::class, 'updateEmail'])->middleware('password.confirm')->name('users.email');
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
Route::post('/events/{event}/invite-batch', [EventController::class, 'inviteBatch'])->middleware('throttle:sensitive')->name('events.invite-batch');
Route::post('/events/{event}/check-in', [EventController::class, 'checkIn'])->name('events.check-in.store');

// Job moderation (SRS 32)
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::post('/jobs/{job}/approve', [JobController::class, 'approve'])->name('jobs.approve');
Route::post('/jobs/{job}/reject', [JobController::class, 'reject'])->name('jobs.reject');

// Communities and chapters (SRS 27-28)
Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('communities.destroy');
Route::put('/communities/{community}/landing', [CommunityController::class, 'landing'])->name('communities.landing');

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

// Recognition content (SRS 39-41)
Route::get('/achievements', [RecognitionController::class, 'achievements'])->name('achievements.index');
Route::post('/achievements/{achievement}/review', [RecognitionController::class, 'review'])->name('achievements.review');
Route::get('/distinguished-alumni', [RecognitionController::class, 'distinguished'])->name('distinguished.index');
Route::post('/distinguished-alumni', [RecognitionController::class, 'saveDistinguished'])->name('distinguished.store');
Route::put('/distinguished-alumni/{honouree}', [RecognitionController::class, 'saveDistinguished'])->name('distinguished.update');
Route::delete('/distinguished-alumni/{honouree}', [RecognitionController::class, 'destroyDistinguished'])->name('distinguished.destroy');
Route::get('/stories', [RecognitionController::class, 'stories'])->name('stories.index');
Route::get('/stories/create', [RecognitionController::class, 'editStory'])->name('stories.create');
Route::post('/stories', [RecognitionController::class, 'saveStory'])->name('stories.store');
Route::get('/stories/{story}/edit', [RecognitionController::class, 'editStory'])->name('stories.edit');
Route::post('/stories/{story}', [RecognitionController::class, 'saveStory'])->name('stories.update');
Route::delete('/stories/{story}', [RecognitionController::class, 'destroyStory'])->name('stories.destroy');

// Communications (SRS 48-49)
Route::get('/communications', [CampaignController::class, 'index'])->name('communications.index');
Route::get('/communications/create', [CampaignController::class, 'edit'])->name('communications.create');
Route::get('/communications/{campaign}/edit', [CampaignController::class, 'edit'])->name('communications.edit');
Route::post('/communications/preview', [CampaignController::class, 'preview'])->name('communications.preview');
Route::post('/communications', [CampaignController::class, 'save'])->middleware('password.confirm')->name('communications.store');
Route::put('/communications/{campaign}', [CampaignController::class, 'save'])->middleware('password.confirm')->name('communications.update');
Route::post('/communications/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('communications.cancel');

// Donations (SRS 45-47)
Route::get('/donations', [DonationController::class, 'index'])->name('donations.index');
Route::post('/donations/{donation}/refund', [DonationController::class, 'refund'])->middleware('password.confirm')->name('donations.refund');
Route::get('/donations/export', [DonationController::class, 'export'])->middleware(['password.confirm', 'throttle:sensitive'])->name('donations.export');

// Fundraising campaigns (SRS 46)
Route::get('/fundraising', [FundraisingController::class, 'index'])->name('fundraising.index');
Route::post('/fundraising/{campaign}/review', [FundraisingController::class, 'review'])->name('fundraising.review');
Route::post('/fundraising/{campaign}/cancel', [FundraisingController::class, 'cancel'])->middleware('password.confirm')->name('fundraising.cancel');

// Surveys (module 31)
Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index');
Route::get('/surveys/create', [SurveyController::class, 'form'])->name('surveys.create');
Route::post('/surveys', [SurveyController::class, 'save'])->name('surveys.store');
Route::get('/surveys/{survey}/edit', [SurveyController::class, 'form'])->name('surveys.edit');
Route::put('/surveys/{survey}', [SurveyController::class, 'save'])->name('surveys.update');
Route::post('/surveys/{survey}/publish', [SurveyController::class, 'publish'])->name('surveys.publish');
Route::post('/surveys/{survey}/close', [SurveyController::class, 'close'])->name('surveys.close');
Route::get('/surveys/{survey}/results', [SurveyController::class, 'results'])->name('surveys.results');
Route::get('/surveys/{survey}/export', [SurveyController::class, 'export'])->middleware('password.confirm')->name('surveys.export');

// Analytics (SRS 54-55)
Route::get('/analytics', AnalyticsController::class)->name('analytics');

// Public landing page (content.manage).
Route::get('/landing', [LandingController::class, 'index'])->name('landing.index');
Route::put('/landing', [LandingController::class, 'update'])->name('landing.update');
Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');
Route::post('/gallery', [GalleryController::class, 'store'])->name('gallery.store');
Route::put('/gallery/{item}', [GalleryController::class, 'update'])->name('gallery.update');
Route::delete('/gallery/{item}', [GalleryController::class, 'destroy'])->name('gallery.destroy');

// Event and news image galleries (multiple images, one featured).
Route::whereIn('type', ['events', 'stories'])->whereNumber(['id', 'image'])->group(function () {
    Route::post('/media/{type}/{id}/images', [MediaGalleryController::class, 'store'])->name('media.store');
    Route::put('/media/{type}/{id}/images/order', [MediaGalleryController::class, 'reorder'])->name('media.reorder');
    Route::post('/media/{type}/{id}/images/{image}/feature', [MediaGalleryController::class, 'feature'])->name('media.feature');
    Route::post('/media/{type}/{id}/images/{image}/replace', [MediaGalleryController::class, 'replace'])->name('media.replace');
    Route::delete('/media/{type}/{id}/images/{image}', [MediaGalleryController::class, 'destroy'])->name('media.destroy');
});

// Media settings (image size limits).
Route::get('/settings/media', [MediaSettingsController::class, 'edit'])->name('settings.media');
Route::put('/settings/media', [MediaSettingsController::class, 'update'])->name('settings.media.update');
