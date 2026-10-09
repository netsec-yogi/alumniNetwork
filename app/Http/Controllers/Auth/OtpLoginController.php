<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\Captcha;
use App\Services\Auth\EmailOtpLogin;
use App\Services\Auth\OtpLoginSettings;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

/**
 * Email OTP sign-in for alumni. Rules live in EmailOtpLogin; this keeps the
 * pending request in the session and makes every outcome look alike.
 */
class OtpLoginController extends Controller
{
    private const PENDING = 'otp_login.pending';

    public const SENT_MESSAGE = 'If an eligible alumni account exists for this email address, an OTP has been sent.';

    public const FAILED_MESSAGE = 'We could not process the OTP request at this time. Please try again later.';

    public function __construct(private readonly EmailOtpLogin $otp, private readonly OtpLoginSettings $settings) {}

    public function captcha(Captcha $captcha): Response
    {
        return response($captcha->issue(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function store(Request $request, Captcha $captcha): RedirectResponse
    {
        // Explicit target: the CAPTCHA image request can be the session's "previous URL".
        try {
            $data = $request->validate([
                'email' => ['required', 'string', 'email', 'max:255'],
                'captcha' => ['required', 'string', 'max:10'],
            ]);

            if (! $captcha->check($data['captcha'])) {
                throw ValidationException::withMessages(['captcha' => 'The characters entered don’t match the image. Please try again.']);
            }
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('login', ['mode' => 'otp']));
        }

        return $this->padded(function () use ($data, $request) {
            $result = $this->otp->request($data['email'], $request);
            $request->session()->put(self::PENDING, $result['pending'] + ['resends' => 0]);

            return $this->afterRequest($result['outcome']);
        });
    }

    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        if (! $pending) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/OtpVerify', [
            'maskedEmail' => $pending['masked'],
            // Seconds, not timestamps, so the countdown ignores the device clock.
            'expiresIn' => max(0, $pending['expires_at'] - now()->timestamp),
            'resendIn' => max(0, $pending['resend_at'] - now()->timestamp),
            'length' => $this->settings->get('length'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        if (! $pending) {
            return redirect()->route('login');
        }
        $length = $this->settings->get('length');
        $request->validate(['code' => ['required', 'string', "regex:/^\\d{{$length}}$/"]], ['code.regex' => "Enter the {$length}-digit OTP from your email."]);

        $result = $this->otp->verify($pending, $request->string('code')->toString());
        $request->session()->put(self::PENDING, $result['pending'] + $pending);

        if ($result['user'] === null) {
            throw ValidationException::withMessages(['code' => $result['error']]);
        }

        $user = $result['user'];
        $request->session()->forget(self::PENDING);

        // Two-factor still applies: hand over to Fortify's challenge as a password sign-in would.
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        if (! $pending) {
            return redirect()->route('login');
        }
        if ($pending['resend_at'] > now()->timestamp) {
            throw ValidationException::withMessages(['code' => 'Please wait '.($pending['resend_at'] - now()->timestamp).' seconds before requesting a new OTP.']);
        }
        if ($pending['resends'] >= $this->settings->get('max_requests_per_hour')) {
            throw ValidationException::withMessages(['code' => 'Too many OTP requests. Please try again later.']);
        }

        return $this->padded(function () use ($pending, $request) {
            $result = $this->otp->request($pending['email'], $request, isResend: true);
            $request->session()->put(self::PENDING, $result['pending'] + ['resends' => $pending['resends'] + 1]);

            return $this->afterRequest($result['outcome']);
        });
    }

    private function afterRequest(string $outcome): RedirectResponse
    {
        if ($outcome === EmailOtpLogin::SEND_FAILED) {
            request()->session()->forget(self::PENDING);

            return redirect()->route('login', ['mode' => 'otp'])->with('error', self::FAILED_MESSAGE);
        }

        return redirect()->route('login.otp.show')->with('status', self::SENT_MESSAGE);
    }

    /** Answer no sooner than the configured minimum, so a skipped send takes as long as a real one. */
    private function padded(Closure $handle): RedirectResponse
    {
        $started = hrtime(true);
        $response = $handle();
        $remaining = config('security.otp_min_response_ms') * 1000 - intdiv(hrtime(true) - $started, 1000);
        if ($remaining > 0) {
            usleep($remaining);
        }

        return $response;
    }
}
