<?php

namespace App\Mail;

use App\Models\Donation;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DonationReceiptMail extends Mailable
{
    public function __construct(public Donation $donation, private readonly string $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Thank you — receipt {$this->donation->receipt_number}");
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Dear '.e($this->donation->donor_name).',</p>'
            .'<p>Thank you for your gift of <strong>'.e($this->donation->formattedAmount()).'</strong> to ABV-IIITM Gwalior. Your receipt is attached.</p>'
            .'<p>With gratitude,<br>Alumni Relations, ABV-IIITM Gwalior</p>');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, "receipt-{$this->donation->reference}.pdf")->withMime('application/pdf')];
    }
}
