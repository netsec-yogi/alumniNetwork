<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Services\AuditLogger;
use App\Services\DonationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Donation records, refunds and exports (SRS 45-47). Financial data: least privilege, everything audited. */
class DonationController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
            'category' => ['nullable', Rule::in(array_keys(config('payments.categories')))],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }

    private function query(array $f): Builder
    {
        return Donation::query()
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('donor_name', 'like', '%'.addcslashes($v, '%_\\').'%')
                ->orWhere('donor_email', 'like', '%'.addcslashes($v, '%_\\').'%')->orWhere('reference', $v)->orWhere('receipt_number', $v)))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<', now()->parse($v)->addDay()));
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::DonationsView->value), 403);
        $f = $this->filters($request);

        return Inertia::render('Admin/Donations/Index', [
            'donations' => $this->query($f)->with('receipt')->latest()->paginate(30)->withQueryString()->through(fn (Donation $d) => [
                'id' => $d->id, 'reference' => $d->reference, 'donor' => $d->donor_name, 'email' => $d->donor_email,
                'amount' => $d->formattedAmount(), 'category' => config('payments.categories')[$d->category] ?? $d->category,
                'status' => $d->status, 'date' => ($d->paid_at ?? $d->created_at)->format('j M Y, H:i'), 'receipt_number' => $d->receipt_number,
                'receipt_url' => $d->receipt?->url(), 'pan' => $d->maskedPan(), 'wants_80g' => $d->wants_80g, 'refund_reason' => $d->refund_reason,
            ]),
            'byCategory' => Donation::where('status', 'paid')->selectRaw('category, count(*) as gifts, sum(amount_paise) as paise')->groupBy('category')->get()
                ->map(fn ($r) => ['label' => config('payments.categories')[$r->category] ?? $r->category, 'value' => (int) floor($r->paise / 100), 'gifts' => (int) $r->gifts]),
            'totals' => [
                'raised' => (int) floor(Donation::where('status', 'paid')->sum('amount_paise') / 100),
                'donors' => Donation::where('status', 'paid')->distinct()->count('donor_email'),
                'thisFy' => (int) floor(Donation::where('status', 'paid')->where('paid_at', '>=', now()->month >= 4 ? now()->startOfYear()->month(4) : now()->subYear()->startOfYear()->month(4))->sum('amount_paise') / 100),
            ],
            'filters' => (object) $f,
            'categories' => collect(config('payments.categories'))->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'can' => ['refund' => $request->user()->can(Permission::DonationsRefund->value), 'export' => $request->user()->can(Permission::DonationsExport->value)],
        ]);
    }

    public function refund(Request $request, Donation $donation, DonationService $service): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::DonationsRefund->value), 403);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'];

        try {
            $service->refund($request->user(), $donation, $reason);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Refunded {$donation->formattedAmount()}.");
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()->can(Permission::DonationsExport->value), 403);
        $f = $this->filters($request);
        $audit->record('donations.exported', 'donations', null, null, ['filters' => $f]);
        $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;

        return response()->streamDownload(function () use ($f, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Receipt', 'Date', 'Donor', 'Email', 'PAN', '80G', 'Category', 'Amount (INR)', 'Status', 'Payment ID']);
            $this->query($f)->orderBy('id')->chunkById(500, function ($rows) use ($out, $safe) {
                foreach ($rows as $d) {
                    fputcsv($out, array_map($safe, [
                        $d->reference, $d->receipt_number, ($d->paid_at ?? $d->created_at)->format('Y-m-d H:i'), $d->donor_name, $d->donor_email,
                        $d->pan, $d->wants_80g ? 'yes' : 'no', $d->category, number_format($d->amountRupees(), 2, '.', ''), $d->status, $d->gateway_payment_id,
                    ]));
                }
            });
            fclose($out);
        }, 'donations-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
