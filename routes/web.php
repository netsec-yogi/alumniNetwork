<?php

use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MentoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SessionController;
use App\Models\AlumniProfile;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'alumniCount' => AlumniProfile::verified()->count(),
]))->name('home');

// Events: browsable without signing in when the event is public (SRS 120).
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/{event}/calendar.ics', [EventController::class, 'calendar'])->name('events.calendar');

/*
 | Authentication routes (login, registration, password reset, email
 | verification, 2FA) are registered by Fortify; see config/fortify.php.
 */

Route::middleware('auth')->group(function () {
    // Security settings stay reachable before email verification, so a user
    // can always secure their account.
    Route::get('/profile/security', [SecurityController::class, 'show'])->name('profile.security');

    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/account', [ProfileController::class, 'updateAccount'])
            ->middleware('throttle:sensitive')->name('profile.account.update');

        Route::get('/profile/sessions', [SessionController::class, 'index'])->name('profile.sessions');
        Route::delete('/profile/sessions/others', [SessionController::class, 'destroyOthers'])
            ->middleware('throttle:sensitive')->name('profile.sessions.destroy-others');
        Route::delete('/profile/sessions/{key}', [SessionController::class, 'destroy'])
            ->where('key', '[a-f0-9]{64}')->middleware('throttle:sensitive')->name('profile.sessions.destroy');

        Route::middleware('throttle:search')->group(function () {
            Route::get('/directory', [DirectoryController::class, 'index'])->name('directory');
            Route::get('/alumni/{profile}', [DirectoryController::class, 'show'])->name('alumni.show');
        });

        // Notifications (SRS 50)
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{id}/open', [NotificationController::class, 'open'])->whereUuid('id')->name('notifications.open');

        // Alumni Connect (SRS 24)
        Route::get('/connections', [ConnectionController::class, 'index'])->name('connections.index');
        Route::post('/alumni/{profile}/connect', [ConnectionController::class, 'store'])->middleware('throttle:connections')->name('connections.store');
        Route::post('/connections/{connection}/accept', [ConnectionController::class, 'accept'])->name('connections.accept');
        Route::post('/connections/{connection}/decline', [ConnectionController::class, 'decline'])->name('connections.decline');
        Route::delete('/connections/{connection}', [ConnectionController::class, 'destroy'])->name('connections.destroy');
        Route::post('/alumni/{profile}/follow', [ConnectionController::class, 'follow'])->name('alumni.follow');
        Route::delete('/alumni/{profile}/follow', [ConnectionController::class, 'unfollow'])->name('alumni.unfollow');
        Route::post('/alumni/{profile}/block', [ConnectionController::class, 'block'])->name('alumni.block');
        Route::delete('/blocks/{user}', [ConnectionController::class, 'unblock'])->name('blocks.destroy');

        // Event registration (SRS 36)
        Route::post('/events/{event}/register', [EventController::class, 'register'])->middleware('throttle:registrations')->name('events.register');
        Route::delete('/events/{event}/register', [EventController::class, 'cancel'])->name('events.cancel');
        Route::get('/events/{event}/ticket', [EventController::class, 'ticket'])->name('events.ticket');

        // Career: jobs, internships, referrals (SRS 31-33)
        Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
        Route::post('/jobs', [JobController::class, 'store'])->middleware('throttle:posts')->name('jobs.store');
        Route::get('/jobs/referrals', [JobController::class, 'referrals'])->name('jobs.referrals');
        Route::whereNumber('job')->group(function () {
            Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
            Route::get('/jobs/{job}/edit', [JobController::class, 'edit'])->name('jobs.edit');
            Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
            Route::post('/jobs/{job}/close', [JobController::class, 'close'])->name('jobs.close');
            Route::post('/jobs/{job}/referrals', [JobController::class, 'requestReferral'])->middleware('throttle:connections')->name('jobs.referrals.store');
        });
        Route::post('/referrals/{referral}/respond', [JobController::class, 'respondReferral'])->name('jobs.referrals.respond');

        // Mentoring (SRS 29-30)
        Route::get('/mentoring', [MentoringController::class, 'index'])->name('mentoring.index');
        Route::get('/mentoring/find', [MentoringController::class, 'find'])->middleware('throttle:search')->name('mentoring.find');
        Route::get('/mentoring/profile', [MentoringController::class, 'editProfile'])->name('mentoring.profile');
        Route::put('/mentoring/profile', [MentoringController::class, 'saveProfile'])->name('mentoring.profile.update');
        Route::post('/mentoring/mentors/{mentor}', [MentoringController::class, 'store'])->middleware('throttle:connections')->name('mentoring.store');
        Route::post('/mentoring/{mentorship}/respond', [MentoringController::class, 'respond'])->name('mentoring.respond');
        Route::post('/mentoring/{mentorship}/complete', [MentoringController::class, 'complete'])->name('mentoring.complete');
        Route::post('/mentoring/{mentorship}/cancel', [MentoringController::class, 'cancel'])->name('mentoring.cancel');

        // Feed (SRS 26)
        Route::get('/feed', [FeedController::class, 'index'])->name('feed');
        Route::post('/posts', [FeedController::class, 'store'])->middleware('throttle:posts')->name('posts.store');
        Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
        Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::post('/posts/{post}/like', [PostController::class, 'like'])->name('posts.like');
        Route::post('/posts/{post}/save', [PostController::class, 'save'])->name('posts.save');
        Route::post('/posts/{post}/pin', [PostController::class, 'pin'])->name('posts.pin');
        Route::post('/posts/{post}/comments', [PostController::class, 'comment'])->middleware('throttle:posts')->name('posts.comments.store');
        Route::delete('/comments/{comment}', [PostController::class, 'destroyComment'])->name('comments.destroy');

        // Communities and chapters (SRS 27-28)
        Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
        Route::get('/communities/{community}', [CommunityController::class, 'show'])->name('communities.show');
        Route::post('/communities/{community}/join', [CommunityController::class, 'join'])->name('communities.join');
        Route::delete('/communities/{community}/join', [CommunityController::class, 'leave'])->name('communities.leave');
        Route::get('/communities/{community}/members', [CommunityController::class, 'members'])->name('communities.members');
        Route::post('/community-members/{member}', [CommunityController::class, 'manage'])->name('communities.members.manage');

        Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
    });
});

/*
 | Fortify can re-display recovery codes at any time. The SRS requires them
 | to be shown once, at generation (see SecurityController), so the read
 | endpoint is shadowed. Regenerating new codes remains available.
 */
Route::get('/user/two-factor-recovery-codes', fn () => abort(404))->middleware('auth');
