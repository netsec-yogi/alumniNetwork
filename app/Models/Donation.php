<?php

namespace App\Models;

use App\Contracts\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Every field is set by DonationService; nothing is mass-assignable. */
class Donation extends Model implements Payable
{
    public const CREATED = 'created';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    protected $hidden = ['pan', 'address'];

    protected function casts(): array
    {
        return [
            'pan' => 'encrypted',
            'address' => 'encrypted',
            'wants_80g' => 'boolean',
            'is_anonymous' => 'boolean',
            'amount_paise' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FundraisingCampaign::class, 'fundraising_campaign_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'receipt_file_id');
    }

    public function amountRupees(): float
    {
        return $this->amount_paise / 100;
    }

    public function formattedAmount(): string
    {
        return '₹'.number_format($this->amountRupees(), $this->amount_paise % 100 ? 2 : 0);
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
        return 'Donation to ABV-IIITM Gwalior — '.(config('payments.categories')[$this->category] ?? $this->category);
    }

    public function payer(): array
    {
        return ['name' => $this->donor_name, 'email' => $this->donor_email, 'phone' => $this->donor_phone];
    }

    public function paymentReturnUrl(): string
    {
        return route('donations.return', ['donation' => $this->reference]);
    }

    public function testCheckoutUrl(): string
    {
        return route('payments.test-checkout', ['type' => 'donation', 'reference' => $this->reference]);
    }

    public function maskedPan(): ?string
    {
        return $this->pan ? str_repeat('X', 6).substr($this->pan, -4) : null;
    }
}
