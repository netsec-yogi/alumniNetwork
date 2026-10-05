<?php

namespace App\Providers;

use App\Listeners\SecurityEventSubscriber;
use App\Models\AlumniProfile;
use App\Models\Community;
use App\Models\Connection;
use App\Models\EventRegistration;
use App\Models\JobPosting;
use App\Models\JobReferralRequest;
use App\Models\MentorshipRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Services\ConnectionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Holds a per-request cache of connection ids.
        $this->app->scoped(ConnectionService::class);
    }

    public function boot(): void
    {
        // SRS 15: long passphrases over composition rules. Breached-password
        // checks call the HIBP range API (k-anonymity), so only in production.
        Password::defaults(fn () => Password::min(config('security.password.min_length'))
            ->max(config('security.password.max_length'))
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));

        // Lazy loading and silently discarded attributes are bugs; surface
        // them during development rather than as N+1 queries in production.
        // (Not "missing attributes": freshly created models legitimately lack
        // columns that were left to their database defaults.)
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if (config('security.force_https')) {
            URL::forceScheme('https');
        }

        Event::subscribe(SecurityEventSubscriber::class);

        // Short, stable type names in polymorphic columns (reports,
        // notifications, roles) instead of PHP class names.
        Relation::enforceMorphMap([
            'user' => User::class,
            'alumni_profile' => AlumniProfile::class,
            'connection' => Connection::class,
            'event' => \App\Models\Event::class,
            'event_registration' => EventRegistration::class,
            'job_posting' => JobPosting::class,
            'job_referral_request' => JobReferralRequest::class,
            'mentorship_request' => MentorshipRequest::class,
            'community' => Community::class,
            'post' => Post::class,
            'post_comment' => PostComment::class,
        ]);

        $this->registerRateLimiters();

        Gate::define('access-admin', fn (User $user) => $user->canAccessAdmin());
    }

    /** Abuse limits on member actions (SRS 66). Auth limits live in FortifyServiceProvider. */
    private function registerRateLimiters(): void
    {
        $by = fn (Request $request, string $key) => $key.':'.($request->user()?->id ?: $request->ip());

        RateLimiter::for('connections', fn (Request $r) => [Limit::perHour(30)->by($by($r, 'conn-h')), Limit::perDay(80)->by($by($r, 'conn-d'))]);
        RateLimiter::for('reports', fn (Request $r) => Limit::perHour(20)->by($by($r, 'report')));
        RateLimiter::for('posts', fn (Request $r) => [Limit::perMinute(5)->by($by($r, 'post-m')), Limit::perDay(100)->by($by($r, 'post-d'))]);
        RateLimiter::for('registrations', fn (Request $r) => Limit::perMinute(10)->by($by($r, 'event-reg')));
    }
}
