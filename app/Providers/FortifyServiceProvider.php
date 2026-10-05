<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\Programme;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            DisableTwoFactorAuthentication::class,
            \App\Actions\Fortify\DisableTwoFactorAuthentication::class,
        );
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
        Fortify::authenticateUsing(fn (Request $request) => app(AuthenticateUser::class)($request));

        $this->registerViews();
        $this->registerRateLimiters();
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('Auth/Register', [
            'programmes' => Programme::active()->orderBy('name')->get(['id', 'name', 'code']),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('Auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Auth/ResetPassword', [
            'email' => $request->input('email'),
            'token' => $request->route('token'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('Auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));
    }

    /** SRS 66: every unauthenticated entry point is throttled. */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));

            // Fortify throttles login with plain ThrottleRequests, which fires
            // no event; record the hit and answer in the form's own terms.
            $onLimit = function (Request $request, array $headers) {
                event(new Lockout($request));

                return back()
                    ->withInput($request->only(Fortify::username()))
                    ->withErrors([Fortify::username() => __('Too many sign-in attempts. Please try again in :seconds seconds.', ['seconds' => $headers['Retry-After'] ?? 60])])
                    ->setStatusCode(302);
            };

            return [
                // One account from one place...
                Limit::perMinute(5)->by($email.'|'.$request->ip())->response($onLimit),
                // ...and one place across many accounts (password spraying).
                // Generous, because campus NAT puts many people behind one IP.
                Limit::perMinute(30)->by('ip:'.$request->ip())->response($onLimit),
            ];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by('2fa:'.$request->session()->get('login.id')));

        // Fortify's default limiter name for password reset / verification
        // mail is the generic 'throttle:6,1'; these are tighter and named.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by('reset:'.Str::lower((string) $request->input('email'))),
            Limit::perHour(20)->by('reset-ip:'.$request->ip()),
        ]);

        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));

        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(10)->by('sensitive:'.($request->user()?->id ?: $request->ip())));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by('search:'.($request->user()?->id ?: $request->ip())));
    }
}
