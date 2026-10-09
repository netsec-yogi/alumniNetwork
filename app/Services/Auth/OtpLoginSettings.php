<?php

namespace App\Services\Auth;

use App\Models\SiteSetting;

/** Admin-configurable Email OTP sign-in parameters, clamped to safe bounds. */
class OtpLoginSettings
{
    /** key => [default, min, max, label, unit] */
    public const FIELDS = [
        'length' => [6, 6, 8, 'OTP length', 'digits'],
        'validity_minutes' => [15, 5, 30, 'OTP validity', 'minutes'],
        'max_attempts' => [5, 3, 10, 'Maximum verification attempts', 'attempts'],
        'resend_cooldown_seconds' => [60, 30, 600, 'Resend cooldown', 'seconds'],
        'max_requests_per_hour' => [5, 1, 20, 'Maximum OTP requests per email per hour', 'requests'],
    ];

    /** @return array<string, int> */
    public function all(): array
    {
        $saved = SiteSetting::get('otp_login', []);

        return collect(self::FIELDS)->map(fn ($f, $k) => $this->clamp($k, (int) ($saved[$k] ?? $f[0])))->all();
    }

    public function get(string $key): int
    {
        return $this->all()[$key];
    }

    public function save(array $values): void
    {
        SiteSetting::put('otp_login', collect(self::FIELDS)->map(fn ($f, $k) => $this->clamp($k, (int) ($values[$k] ?? $f[0])))->all());
    }

    private function clamp(string $key, int $value): int
    {
        [, $min, $max] = self::FIELDS[$key];

        return max($min, min($max, $value));
    }
}
