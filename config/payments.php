<?php

/*
|--------------------------------------------------------------------------
| Donations and payments (SRS 45-47)
|--------------------------------------------------------------------------
|
| PAYMENT_GATEWAY=fake gives a local test checkout (never in production).
| PAYMENT_GATEWAY=razorpay uses Razorpay Payment Links: a hosted checkout,
| so card/UPI details never touch this application (PCI DSS scope stays
| minimal). Configure the webhook in the Razorpay dashboard to
| https://<host>/webhooks/payments/razorpay for "payment_link.paid".
|
*/

return [

    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'fake' => [
        // Only signs the local test checkout; not a real credential.
        'secret' => env('FAKE_GATEWAY_SECRET', 'local-test-gateway-secret'),
    ],

    'currency' => 'INR',
    'min_amount' => (int) env('DONATION_MIN_INR', 100),
    'max_amount' => (int) env('DONATION_MAX_INR', 1000000),

    // Income Tax Rule 114B: PAN is required at or above this amount.
    'pan_required_from' => 50000,

    'categories' => [
        'scholarship' => 'Scholarships',
        'research' => 'Research',
        'infrastructure' => 'Infrastructure',
        'student_welfare' => 'Student welfare',
        'innovation' => 'Innovation & incubation',
        'entrepreneurship' => 'Entrepreneurship',
        'general' => 'General fund',
        'endowment' => 'Endowment',
    ],

    // Printed on receipts. Fill in from the institute's registration
    // certificates before accepting real donations.
    'receipt' => [
        'institution' => env('RECEIPT_INSTITUTION', 'ABV-Indian Institute of Information Technology and Management, Gwalior'),
        'address' => env('RECEIPT_ADDRESS', 'Morena Link Road, Gwalior, Madhya Pradesh 474015'),
        'pan' => env('RECEIPT_INSTITUTION_PAN', 'TO BE CONFIGURED'),
        'section_80g' => env('RECEIPT_80G_REGISTRATION', 'TO BE CONFIGURED'),
        'signatory' => env('RECEIPT_SIGNATORY', 'Dean (Alumni Relations)'),
    ],
];
