<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use App\Services\AlumniVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Manual verification queue (SRS 20). */
class VerificationController extends Controller
{
    public function __construct(private readonly AlumniVerificationService $verification) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', VerificationRequest::class);

        $status = $request->validate([
            'status' => ['nullable', Rule::enum(VerificationStatus::class)],
        ])['status'] ?? VerificationStatus::Pending->value;

        $requests = VerificationRequest::query()
            ->with([
                'profile' => fn ($q) => $q->withTrashed(),
                'profile.user:id,name,email,email_verified_at',
                'profile.programme:id,name',
                'matchedRecord.programme:id,name',
                'reviewer:id,name',
            ])
            ->where('status', $status)
            ->oldest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (VerificationRequest $r) use ($request) {
                $p = $r->profile;
                $m = $r->matchedRecord;

                return [
                    'id' => $r->id,
                    'submitted_at' => $r->created_at->toDayDateTimeString(),
                    'method' => $r->method,
                    'note' => $r->applicant_note,
                    'claim' => [
                        'name' => $p->user->name,
                        'email' => $p->user->email,
                        'email_verified' => $p->user->email_verified_at !== null,
                        'roll_number' => $p->roll_number,
                        'programme' => $p->programme->name,
                        'admission_year' => $p->admission_year,
                        'graduation_year' => $p->graduation_year,
                    ],
                    // The institute's record for the claimed roll number, if any,
                    // so the officer can compare side by side.
                    'record' => $m ? [
                        'name' => $m->name,
                        'roll_number' => $m->roll_number,
                        'programme' => $m->programme->name,
                        'admission_year' => $m->admission_year,
                        'graduation_year' => $m->graduation_year,
                    ] : null,
                    'decision' => $r->reviewed_at ? [
                        'by' => $r->reviewer?->name ?? 'Automatic match',
                        'at' => $r->reviewed_at->toDayDateTimeString(),
                        'reason' => $r->decision_reason,
                    ] : null,
                    'can_decide' => $r->status === VerificationStatus::Pending && $request->user()->can('decide', $r),
                ];
            });

        return Inertia::render('Admin/Verification/Index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => VerificationRequest::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function approve(Request $request, VerificationRequest $verificationRequest): RedirectResponse
    {
        $this->authorize('decide', $verificationRequest);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return $this->attempt(fn () => $this->verification->approve($verificationRequest, $request->user(), $data['reason'] ?? null), 'Verified.');
    }

    public function reject(Request $request, VerificationRequest $verificationRequest): RedirectResponse
    {
        $this->authorize('decide', $verificationRequest);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->attempt(fn () => $this->verification->reject($verificationRequest, $request->user(), $data['reason']), 'Rejected; the applicant has been told why.');
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
