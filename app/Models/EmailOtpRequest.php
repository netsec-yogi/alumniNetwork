<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One emailed sign-in code. Created and consumed only by EmailOtpLogin;
 * nothing is mass-assignable. `otp_hash` is an HMAC — the code itself is
 * never stored or logged.
 */
class EmailOtpRequest extends Model
{
    protected $hidden = ['otp_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'verified_at' => 'datetime', 'invalidated_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->verified_at === null && $this->invalidated_at === null && $this->expires_at->isFuture();
    }
}
