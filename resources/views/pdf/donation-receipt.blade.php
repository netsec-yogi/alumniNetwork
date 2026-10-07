<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
    .head { border-bottom: 3px solid #0b3d6e; padding-bottom: 10px; margin-bottom: 18px; }
    .org { font-size: 15px; font-weight: bold; color: #0b3d6e; }
    .muted { color: #475569; }
    h1 { font-size: 16px; margin: 0 0 12px; letter-spacing: 1px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 6px 8px; border: 1px solid #cbd5e1; vertical-align: top; }
    td.label { width: 32%; background: #f1f5f9; font-weight: bold; }
    .amount { font-size: 14px; font-weight: bold; }
    .note { margin-top: 16px; font-size: 10px; color: #334155; }
    .sign { margin-top: 40px; text-align: right; }
</style>
</head>
<body>
    <div class="head">
        <div class="org">{{ $org['institution'] }}</div>
        <div class="muted">{{ $org['address'] }}</div>
        <div class="muted">PAN: {{ $org['pan'] }} · 80G registration: {{ $org['section_80g'] }}</div>
    </div>

    <h1>DONATION RECEIPT</h1>
    <table>
        <tr><td class="label">Receipt number</td><td>{{ $d->receipt_number }}</td></tr>
        <tr><td class="label">Date</td><td>{{ $d->paid_at->timezone(config('app.timezone'))->format('d F Y') }}</td></tr>
        <tr><td class="label">Received from</td><td>{{ $d->donor_name }}</td></tr>
        @if ($d->address)<tr><td class="label">Address</td><td>{{ $d->address }}</td></tr>@endif
        @if ($d->pan)<tr><td class="label">Donor PAN</td><td>{{ $d->pan }}</td></tr>@endif
        <tr><td class="label">Amount</td><td class="amount">{{ $d->formattedAmount() }}</td></tr>
        <tr><td class="label">Amount in words</td><td>{{ $amountWords }}</td></tr>
        <tr><td class="label">Towards</td><td>{{ $category }}</td></tr>
        <tr><td class="label">Mode of payment</td><td>Online ({{ ucfirst($d->gateway) }}), payment ID {{ $d->gateway_payment_id }}</td></tr>
        <tr><td class="label">Reference</td><td>{{ $d->reference }}</td></tr>
    </table>

    <p class="note">
        @if ($d->wants_80g && $d->pan)
            This donation is eligible for deduction under Section 80G of the Income-tax Act, 1961, subject to the conditions therein, as per the registration noted above.
        @else
            A PAN was not provided, so this receipt cannot be used to claim a deduction under Section 80G.
        @endif
        This is a computer-generated receipt and does not require a physical signature.
    </p>

    <div class="sign">For {{ $org['institution'] }}<br><br>{{ $org['signatory'] }}</div>
</body>
</html>
