<?php

namespace App\Providers;

use App\Listeners\SecurityEventSubscriber;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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

        Gate::define('access-admin', fn (User $user) => $user->canAccessAdmin());
    }
}
