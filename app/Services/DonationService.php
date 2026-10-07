<?php

namespace App\Services;

use App\Jobs\GenerateDonationReceipt;
use App\Models\Donation;
use App\Models\EngagementActivity;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Donations (SRS 45-47). State changes are idempotent and serialised on the
 * donation row: the return redirect and the webhook may both arrive, in
 * either order, possibly more than once, and the donation is still marked
 * paid exactly once with exactly one receipt number.
 */
class DonationService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EngagementRecorder $engagement,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{donation: Donation, redirect: string} */
    public function start(array $data, ?User $user, ?string $ip): array
    {
        $donation = new Donation;
        $donation->forceFill([
            'reference' => 'DON-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'user_id' => $user?->id,
            'donor_name' => $data['donor_name'],
            'donor_email' => Str::lower($data['donor_email']),
            'donor_phone' => $data['donor_phone'] ?? null,
            'pan' => isset($data['pan']) ? Str::upper($data['pan']) : null,
            'address' => $data['address'] ?? null,
            'wants_80g' => (bool) ($data['wants_80g'] ?? false),
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'category' => $data['category'],
            'fundraising_campaign_id' => $data['fundraising_campaign_id'] ?? null,
            'amount_paise' => (int) round($data['amount'] * 100),
            'currency' => 'INR',
            'status' => Donation::CREATED,
            'gateway' => $this->gateway->name(),
            'ip_address' => $ip,
        ])->save();

        $redirect = $this->gateway->checkout($donation);
        $donation->forceFill(['status' => Donation::PENDING])->save();

        return ['donation' => $donation, 'redirect' => $redirect];
    }

    public function markPaid(Donation $donation, ?string $paymentId, ?int $amountPaise = null): Donation
    {
        $paid = DB::transaction(function () use ($donation, $paymentId, $amountPaise) {
            $d = Donation::whereKey($donation->id)->lockForUpdate()->firstOrFail();

            if ($d->status === Donation::PAID || $d->status === Donation::REFUNDED) {
                return null; // already handled
            }
            if ($amountPaise !== null && $amountPaise !== $d->amount_paise) {
                throw new InvalidArgumentException("Amount mismatch for {$d->reference}.");
            }

            $d->forceFill([
                'status' => Donation::PAID,
                'gateway_payment_id' => $paymentId,
                'paid_at' => now(),
                'receipt_number' => $this->nextReceiptNumber(now()),
            ])->save();

            return $d;
        });

        if ($paid) {
            if ($paid->user) {
                $this->engagement->record($paid->user, 'DONATION', EngagementActivity::MODE_PHILANTHROPIC, $paid, null, ['amount' => $paid->amountRupees(), 'category' => $paid->category]);
            }
            $this->audit->record('donation.paid', 'donations', $paid, null, ['amount' => $paid->amountRupees(), 'receipt' => $paid->receipt_number], $paid->user_id);
            GenerateDonationReceipt::dispatch($paid);

            return $paid;
        }

        return $donation->fresh();
    }

    public function markFailed(Donation $donation): void
    {
        Donation::whereKey($donation->id)->whereIn('status', [Donation::CREATED, Donation::PENDING])->update(['status' => Donation::FAILED]);
    }

    public function refund(User $actor, Donation $donation, string $reason): void
    {
        if ($donation->status !== Donation::PAID) {
            throw new InvalidArgumentException('Only paid donations can be refunded.');
        }

        $this->gateway->refund($donation);
        $donation->forceFill(['status' => Donation::REFUNDED, 'refunded_at' => now(), 'refund_reason' => $reason])->save();

        // A refunded gift no longer counts towards engagement.
        EngagementActivity::where(['entity_type' => 'donation', 'entity_id' => $donation->id])->delete();
        $this->audit->record('donation.refunded', 'donations', $donation, null, ['amount' => $donation->amountRupees(), 'reason' => $reason], $actor);
    }

    /** Indian financial year, April to March: e.g. "2026-27". */
    public static function financialYear(Carbon $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return $start.'-'.substr((string) ($start + 1), -2);
    }

    /** Gap-free and unique: allocated under a row lock in the caller's transaction. */
    private function nextReceiptNumber(Carbon $date): string
    {
        $fy = self::financialYear($date);
        DB::table('receipt_sequences')->insertOrIgnore(['financial_year' => $fy, 'last_number' => 0]);
        $row = DB::table('receipt_sequences')->where('financial_year', $fy)->lockForUpdate()->first();
        $next = $row->last_number + 1;
        DB::table('receipt_sequences')->where('financial_year', $fy)->update(['last_number' => $next]);

        return sprintf('IIITM/ALU/%s/%06d', $fy, $next);
    }
}
