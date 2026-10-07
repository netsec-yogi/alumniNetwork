<?php

namespace App\Jobs;

use App\Mail\DonationReceiptMail;
use App\Models\Donation;
use App\Models\StoredFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Builds the PDF receipt (SRS 47), stores it privately and emails it to the donor. */
class GenerateDonationReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Donation $donation) {}

    public function handle(): void
    {
        $d = $this->donation->fresh();
        if ($d->status !== Donation::PAID || $d->receipt_file_id) {
            return;
        }

        $words = ucfirst((new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT))->format((int) floor($d->amountRupees())));
        $pdf = Pdf::loadView('pdf.donation-receipt', [
            'd' => $d,
            'org' => config('payments.receipt'),
            'category' => config('payments.categories')[$d->category] ?? $d->category,
            'amountWords' => "Rupees {$words} only",
        ])->setPaper('a4')->output();

        $path = 'files/receipts/'.now()->format('Y/m').'/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, $pdf);

        $file = new StoredFile;
        $file->forceFill([
            'owner_id' => $d->user_id,
            'purpose' => 'donation_receipt',
            'kind' => 'document',
            'path' => $path,
            'original_name' => 'receipt-'.Str::slug($d->receipt_number).'.pdf',
            'mime' => 'application/pdf',
            'size' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
            'visibility' => StoredFile::PRIVATE,
            'scan_status' => 'clean', // generated here, not uploaded
            'attachable_type' => 'donation',
            'attachable_id' => $d->id,
        ])->save();

        $d->forceFill(['receipt_file_id' => $file->id])->save();
        Mail::to($d->donor_email)->send(new DonationReceiptMail($d, $pdf));
    }
}
