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
        'remember_token', 'token', '_token',
    ],

    'audit_retention_days' => (int) env('SECURITY_AUDIT_RETENTION_DAYS', 730),
];
