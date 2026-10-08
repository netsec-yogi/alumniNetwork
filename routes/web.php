<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\CommunicationPreferenceController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FundraisingController;
use App\Http\Controllers\GivingController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MentoringController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentTestController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecognitionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\SpeakerController;
use App\Http\Controllers\StartupController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\VolunteeringController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

// Uploaded files, after an authorisation check (public ones work signed out).
Route::get('/files/{file}/{variant?}', [FileController::class, 'show'])->whereUlid('file')->whereIn('variant', ['thumb'])->name('files.show');

// Public recognition pages (SRS 39-41, 120)
Route::get('/achievements', [RecognitionController::class, 'achievements'])->name('achievements.index');
Route::get('/distinguished-alumni', [RecognitionController::class, 'distinguished'])->name('distinguished.index');
Route::get('/stories', [RecognitionController::class, 'stories'])->name('stories.index');
Route::get('/stories/{story}', [RecognitionController::class, 'story'])->name('stories.show');

// Unsubscribe links in campaign emails: signed, no sign-in needed.
Route::get('/unsubscribe/{user}', [CommunicationPreferenceController::class, 'show'])->middleware('signed')->name('unsubscribe.show');
Route::post('/unsubscribe/{user}', [CommunicationPreferenceController::class, 'unsubscribe'])->middleware(['signed', 'throttle:sensitive'])->name('unsubscribe.store');

// Giving (SRS 45-47). Open to guests; the webhook is authenticated by its signature.
Route::get('/give', [GivingController::class, 'index'])->name('giving.index');
Route::post('/give', [GivingController::class, 'store'])->middleware('throttle:donations')->name('giving.store');
Route::get('/give/return/{donation}', [GivingController::class, 'return'])->name('donations.return');
Route::get('/pay/test-checkout/{type}/{reference}', [PaymentTestController::class, 'show'])->whereIn('type', ['donation', 'event'])->name('payments.test-checkout');
Route::post('/webhooks/payments/{gateway}', [GivingController::class, 'webhook'])->middleware('throttle:webhooks')->name('webhooks.payments');

// Fundraising campaigns (SRS 46): public pages.
Route::get('/campaigns', [FundraisingController::class, 'index'])->name('fundraising.index');
Route::get('/campaigns/{campaign}', [FundraisingController::class, 'show'])->name('fundraising.show');

// Events: browsable without signing in when the event is public (SRS 120).
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/{event}/calendar.ics', [EventController::class, 'calendar'])->name('events.calendar');

/*
 | Authentication routes (login, registration, password reset, email
 | verification, 2FA) are registered by Fortify; see config/fortify.php.
 */

// Sign in with Google / LinkedIn (SRS 10). Providers 404 until configured.
Route::whereIn('provider', ['google', 'linkedin-openid'])->middleware('throttle:sensitive')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->middleware('guest')->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');
    Route::post('/profile/social/{provider}', [SocialLoginController::class, 'link'])->middleware(['auth', 'password.confirm'])->name('social.link');
    Route::delete('/profile/social/{provider}', [SocialLoginController::class, 'unlink'])->middleware(['auth', 'password.confirm'])->name('social.unlink');
});

Route::middleware('auth')->group(function () {
    // Security settings stay reachable before email verification, so a user
    // can always secure their account.
    Route::get('/profile/security', [SecurityController::class, 'show'])->name('profile.security');

    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->middleware('throttle:posts')->name('profile.photo.update');
        Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
        Route::put('/profile/communications', [CommunicationPreferenceController::class, 'update'])->name('profile.communications');
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
        Route::post('/events/{event}/pay', [EventController::class, 'pay'])->name('events.pay');
        Route::get('/event-payments/{reference}/return', [EventController::class, 'paymentReturn'])->name('events.payment-return');
        Route::post('/events/{event}/photos', [EventController::class, 'uploadPhoto'])->middleware('throttle:posts')->name('events.photos.store');
        Route::delete('/event-photos/{photo}', [EventController::class, 'deletePhoto'])->name('events.photos.destroy');

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

        // Messaging (SRS 25)
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{conversation}', [MessageController::class, 'store'])->middleware('throttle:messages')->name('messages.store');
        Route::post('/messages/{conversation}/respond', [MessageController::class, 'respond'])->name('messages.respond');
        Route::post('/alumni/{profile}/message', [MessageController::class, 'start'])->middleware(['throttle:messages', 'throttle:conversations'])->name('messages.start');
        Route::delete('/message/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

        // Achievements (SRS 39)
        Route::get('/my-achievements', [RecognitionController::class, 'mine'])->name('achievements.mine');
        Route::post('/my-achievements', [RecognitionController::class, 'submit'])->middleware('throttle:posts')->name('achievements.submit');
        Route::delete('/my-achievements/{achievement}', [RecognitionController::class, 'withdraw'])->name('achievements.withdraw');

        // Startups (SRS 34)
        Route::get('/startups', [StartupController::class, 'index'])->name('startups.index');
        Route::get('/startups/create', [StartupController::class, 'form'])->name('startups.create');
        Route::post('/startups', [StartupController::class, 'save'])->middleware('throttle:posts')->name('startups.store');
        Route::get('/startups/{startup}', [StartupController::class, 'show'])->name('startups.show');
        Route::get('/startups/{startup}/edit', [StartupController::class, 'form'])->name('startups.edit');
        Route::post('/startups/{startup}', [StartupController::class, 'save'])->name('startups.update');
        Route::post('/startups/{startup}/visibility', [StartupController::class, 'toggleHidden'])->name('startups.visibility');

        // Research collaboration (SRS 42)
        Route::get('/research', [ResearchController::class, 'index'])->name('research.index');
        Route::post('/research', [ResearchController::class, 'store'])->middleware('throttle:posts')->name('research.store');
        Route::get('/research/{opportunity}', [ResearchController::class, 'show'])->name('research.show');
        Route::post('/research/{opportunity}/close', [ResearchController::class, 'close'])->name('research.close');
        Route::post('/research/{opportunity}/interest', [ResearchController::class, 'interest'])->middleware('throttle:connections')->name('research.interest');

        // Speaker network (SRS 43)
        Route::get('/speakers', [SpeakerController::class, 'index'])->middleware('throttle:search')->name('speakers.index');
        Route::get('/speakers/profile', [SpeakerController::class, 'editProfile'])->name('speakers.profile');
        Route::put('/speakers/profile', [SpeakerController::class, 'saveProfile'])->name('speakers.profile.update');
        Route::post('/speakers/{speaker}/invite', [SpeakerController::class, 'invite'])->middleware('throttle:connections')->name('speakers.invite');
        Route::post('/speaker-invitations/{invitation}/respond', [SpeakerController::class, 'respond'])->name('speakers.respond');
        Route::post('/speaker-invitations/{invitation}/delivered', [SpeakerController::class, 'delivered'])->name('speakers.delivered');

        // Volunteering (SRS 44)
        Route::get('/volunteering', [VolunteeringController::class, 'index'])->name('volunteering.index');
        Route::post('/volunteering', [VolunteeringController::class, 'store'])->name('volunteering.store');
        Route::post('/volunteering/{opportunity}/sign-up', [VolunteeringController::class, 'signUp'])->name('volunteering.sign-up');
        Route::get('/volunteering/{opportunity}/manage', [VolunteeringController::class, 'manage'])->name('volunteering.manage');
        Route::post('/volunteering/{opportunity}/close', [VolunteeringController::class, 'close'])->name('volunteering.close');
        Route::post('/volunteer-signups/{signup}/withdraw', [VolunteeringController::class, 'withdraw'])->name('volunteering.withdraw');
        Route::post('/volunteer-signups/{signup}/hours', [VolunteeringController::class, 'logHours'])->name('volunteering.hours');
        Route::post('/volunteer-signups/{signup}/review', [VolunteeringController::class, 'approve'])->name('volunteering.review');

        Route::get('/propose-campaign', [FundraisingController::class, 'form'])->name('fundraising.create');
        Route::post('/propose-campaign', [FundraisingController::class, 'save'])->middleware('throttle:posts')->name('fundraising.store');
        Route::get('/campaigns/{campaign}/edit', [FundraisingController::class, 'form'])->name('fundraising.edit');
        Route::post('/campaigns/{campaign}/edit', [FundraisingController::class, 'save'])->name('fundraising.update');
        Route::post('/campaigns/{campaign}/updates', [FundraisingController::class, 'postUpdate'])->name('fundraising.updates.store');
        // Surveys (module 31)
        Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index');
        Route::get('/surveys/{survey}', [SurveyController::class, 'show'])->name('surveys.show');
        Route::post('/surveys/{survey}', [SurveyController::class, 'submit'])->middleware('throttle:posts')->name('surveys.submit');

        // AI features (SRS 96); each action 404s unless AI is enabled.
        Route::post('/directory/ai-search', [AiController::class, 'search'])->middleware('throttle:ai')->name('directory.ai-search');
        Route::get('/assistant', [AiController::class, 'assistant'])->name('assistant');
        Route::post('/assistant', [AiController::class, 'ask'])->middleware('throttle:ai')->name('assistant.ask');
        Route::delete('/assistant', [AiController::class, 'reset'])->name('assistant.reset');

        Route::get('/my-donations', [GivingController::class, 'mine'])->name('giving.mine');

        Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
    });
});

/*
 | Fortify can re-display recovery codes at any time. The SRS requires them
 | to be shown once, at generation (see SecurityController), so the read
 | endpoint is shadowed. Regenerating new codes remains available.
 */
Route::get('/user/two-factor-recovery-codes', fn () => abort(404))->middleware('auth');
