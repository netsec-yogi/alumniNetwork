<?php

namespace App\Models;

use App\Contracts\Payable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Status, ticket and check-in fields are set by EventRegistrationService only. */
#[Fillable(['event_id', 'user_id'])]
class EventRegistration extends Model implements Payable
{
    public const CONFIRMED = 'confirmed';

    public const WAITLISTED = 'waitlisted';

    public const CANCELLED = 'cancelled';

    /** Seat held until the fee is paid (paid events only). */
    public const PAYMENT_PENDING = 'payment_pending';

    protected $hidden = ['ticket_code'];

    protected function casts(): array
    {
        return [
            'guests' => 'integer',
            'checked_in_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'amount_paise' => 'integer',
            'paid_at' => 'datetime',
            'hold_expires_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function paymentReference(): string
    {
        return $this->reference;
    }

    public function paymentAmountPaise(): int
    {
        return $this->amount_paise;
    }

    public function paymentDescription(): string
    {
        return 'Registration: '.$this->event->title;
    }

    public function payer(): array
    {
        return ['name' => $this->user->name, 'email' => $this->user->email, 'phone' => $this->user->phone];
    }

    public function paymentReturnUrl(): string
    {
        return route('events.payment-return', ['reference' => $this->reference]);
    }

    public function testCheckoutUrl(): string
    {
        return route('payments.test-checkout', ['type' => 'event', 'reference' => $this->reference]);
    }

    public function seats(): int
    {
        return 1 + $this->guests;
    }
}
