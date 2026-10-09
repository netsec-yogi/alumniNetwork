<?php

use App\Enums\RoleName;

/*
|--------------------------------------------------------------------------
| Security policy
|--------------------------------------------------------------------------
|
| Institutional security settings in one place, so that policy changes
| (making 2FA mandatory for alumni, tightening admin timeouts) are a config
| edit rather than a code change. See docs/security.md.
|
*/

return [

    'force_https' => (bool) env('APP_FORCE_HTTPS', false),

    // Send CSP as Report-Only while trialling a policy change (SRS 64).
    'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),

    /*
     | Roles that must enrol in TOTP 2FA before they can use the portal at all
     | (SRS 11). Adding RoleName::Alumni->value here makes it mandatory for
     | alumni too.
     */
    'two_factor_required_roles' => [
        RoleName::SuperAdmin->value,
        RoleName::AlumniAdmin->value,
        RoleName::VerificationOfficer->value,
        RoleName::ChapterAdmin->value,
        RoleName::CommunityModerator->value,
        RoleName::EventManager->value,
        RoleName::FundraisingManager->value,
        RoleName::CareerAdmin->value,
    ],

    /*
     | Email OTP sign-in: every request answers in at least this many
     | milliseconds, so timing doesn't reveal whether an email was sent.
     | Code length, validity, attempts and limits are admin settings.
     */
    'otp_min_response_ms' => (int) env('OTP_MIN_RESPONSE_MS', 1200),

    /*
     | Session timeouts, in minutes (SRS 16). Regular users fall back to
     | SESSION_LIFETIME as the idle timeout. Privileged users (anyone holding
     | a role above) get a shorter idle timeout and an absolute limit after
     | which they must sign in again regardless of activity.
     */
    'privileged_idle_timeout' => (int) env('SECURITY_ADMIN_IDLE_MINUTES', 30),
    'privileged_absolute_timeout' => (int) env('SECURITY_ADMIN_ABSOLUTE_MINUTES', 480),

    /*
     | Account lockout (SRS 67-68). This complements, rather than replaces,
     | the per email+IP rate limiter: the limiter slows one source down, the
     | lockout protects one account from a distributed guess.
     */
    'lockout' => [
        'max_attempts' => (int) env('SECURITY_LOCKOUT_ATTEMPTS', 10),
        'minutes' => (int) env('SECURITY_LOCKOUT_MINUTES', 15),
    ],

    /*
     | Email the account owner when they sign in from a browser that hasn't
     | signed in to the account before: 'privileged' (staff roles), 'all',
     | or 'off'.
     */
    'login_alerts' => env('LOGIN_ALERTS', 'privileged'),

    /*
     | Optional allow-list for /admin: comma-separated IPs or CIDRs, e.g.
     | "14.139.240.0/24,2001:db8::/32". Empty means no restriction.
     */
    'admin_allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_IPS', ''))))),

    'password' => [
        'min_length' => 12,
        'max_length' => 128,
    ],

    /*
     | Request fields that must never reach the audit log or application
     | logs, wherever they appear (SRS 80, 82).
     */
    'redact' => [
        'password', 'password_confirmation', 'current_password',
        'code', 'recovery_code', 'two_factor_secret', 'two_factor_recovery_codes',
        'remember_token', 'token', '_token', 'new_password', 'temporary_password', 'password_hash',
    ],

    /*
     | File uploads (SRS 71-73). With CLAMAV_HOST set, every upload is
     | scanned by clamd before it is stored. UPLOADS_SCAN_REQUIRED=true
     | makes uploads fail closed when the scanner is unreachable or not
     | configured -- set it in production.
     */
    'uploads' => [
        'clamav_host' => env('CLAMAV_HOST'),
        'clamav_port' => (int) env('CLAMAV_PORT', 3310),
        'scan_required' => (bool) env('UPLOADS_SCAN_REQUIRED', false),
        'max_image_kb' => 8192,
        'max_document_kb' => 10240,
        // Portal logos and favicon (SVG ones at most 200 KB).
        'max_logo_kb' => 2048,
        // Decompression-bomb guard: refuse images larger than this many pixels.
        'max_pixels' => 40_000_000,
    ],

    'audit_retention_days' => (int) env('SECURITY_AUDIT_RETENTION_DAYS', 730),
];
