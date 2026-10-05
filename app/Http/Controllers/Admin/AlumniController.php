<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportAlumniRecords;
use App\Models\AlumniProfile;
use App\Models\AuditLog;
use App\Models\CommunityMember;
use App\Models\EngagementActivity;
use App\Models\EventRegistration;
use App\Models\Import;
use App\Models\JobPosting;
use App\Models\MentorshipRequest;
use App\Models\Programme;
use App\Services\AlumniRecordImporter;
use App\Services\AuditLogger;
use App\Services\ConnectionService;
use App\Services\EngagementScore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Alumni CRM: list, 360° record, export, import (SRS 51, 94-95). */
class AlumniController extends Controller
{
    public const EXPORT_LIMIT = 10000;

    public function __construct(private readonly AuditLogger $audit) {}

    private function filtered(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'programme' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(VerificationStatus::class)],
            'company' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function query(array $f): Builder
    {
        $like = fn (string $v) => '%'.addcslashes($v, '%_\\').'%';

        return AlumniProfile::query()
            ->with(['user:id,name,email,phone,last_login_at', 'programme:id,code,name'])
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q->where('roll_number', 'like', $like($t))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like($t))->orWhere('email', 'like', $like($t)))))
            ->when($f['programme'] ?? null, fn ($q, $v) => $q->where('programme_id', $v))
            ->when($f['year'] ?? null, fn ($q, $v) => $q->where('graduation_year', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('verification_status', $v))
            ->when($f['company'] ?? null, fn ($q, $v) => $q->where('company', 'like', $like($v)))
            ->when($f['location'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('city', 'like', $like($v))->orWhere('country', 'like', $like($v))));
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::AlumniView->value), 403);
        $filters = $this->filtered($request);

        return Inertia::render('Admin/Alumni/Index', [
            'alumni' => $this->query($filters)->orderByDesc('graduation_year')->orderBy('id')->paginate(30)->withQueryString()
                ->through(fn (AlumniProfile $p) => [
                    'id' => $p->id,
                    'name' => $p->user->name,
                    'email' => $p->user->email,
                    'roll_number' => $p->roll_number,
                    'programme' => $p->programme->code,
                    'graduation_year' => $p->graduation_year,
                    'company' => $p->company,
                    'location' => collect([$p->city, $p->country])->filter()->implode(', '),
                    'status' => $p->verification_status->value,
                ]),
            'filters' => (object) $filters,
            'programmes' => Programme::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
            'can' => [
                'export' => $request->user()->can(Permission::AlumniExport->value),
                'import' => $request->user()->can(Permission::AlumniImport->value),
            ],
            'exportLimit' => self::EXPORT_LIMIT,
        ]);
    }

    /** The 360° record (SRS 51). Staff see everything here regardless of the alumnus's privacy settings, so viewing is audited. */
    public function show(Request $request, AlumniProfile $alumnus, EngagementScore $score, ConnectionService $connections): Response
    {
        abort_unless($request->user()->can(Permission::AlumniView->value), 403);
        $alumnus->load(['user', 'programme.department', 'record', 'verificationRequests.reviewer:id,name']);
        $user = $alumnus->user;

        $this->audit->record('alumni.viewed', 'alumni', $alumnus);

        return Inertia::render('Admin/Alumni/Show', [
            'alumnus' => [
                'id' => $alumnus->id,
                'name' => $user->name,
                'preferred_name' => $alumnus->preferred_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'account_status' => $user->status->value,
                'last_login' => $user->last_login_at?->diffForHumans(),
                'roll_number' => $alumnus->roll_number,
                'programme' => $alumnus->programme->name,
                'department' => $alumnus->programme->department?->name,
                'batch' => ($alumnus->admission_year ? "{$alumnus->admission_year}–" : '').$alumnus->graduation_year,
                'company' => $alumnus->company,
                'designation' => $alumnus->designation,
                'industry' => $alumnus->industry,
                'location' => collect([$alumnus->city, $alumnus->state, $alumnus->country])->filter()->implode(', '),
                'linkedin_url' => $alumnus->linkedin_url,
                'interests' => collect($alumnus->interests ?? [])->map(fn ($i) => AlumniProfile::INTERESTS[$i] ?? $i)->values(),
                'status' => $alumnus->verification_status->value,
                'institute_record' => $alumnus->record?->only(['roll_number', 'name', 'graduation_year']),
                'joined' => $alumnus->created_at->format('j M Y'),
            ],
            'score' => [
                'total' => $score->forProfile($alumnus->id),
                'year' => $score->forProfile($alumnus->id, now()->subYear()),
            ],
            'byMode' => EngagementActivity::where('alumni_profile_id', $alumnus->id)
                ->selectRaw('engagement_mode, count(*) as n')->groupBy('engagement_mode')->pluck('n', 'engagement_mode'),
            'timeline' => EngagementActivity::where('alumni_profile_id', $alumnus->id)->latest('activity_date')->latest('id')->limit(25)->get()
                ->map(fn ($a) => ['type' => $a->activity_type, 'mode' => $a->engagement_mode, 'date' => $a->activity_date->format('j M Y'), 'points' => config("engagement.weights.{$a->activity_type}", 0)]),
            'counts' => [
                'connections' => $connections->connectedIds($user->id)->count(),
                'events' => EventRegistration::where('user_id', $user->id)->whereNotNull('checked_in_at')->count(),
                'mentoring' => MentorshipRequest::where('mentor_id', $user->id)->whereIn('status', ['accepted', 'completed'])->count(),
                'jobs' => JobPosting::where('posted_by', $user->id)->count(),
                'communities' => CommunityMember::where('user_id', $user->id)->where('status', 'active')->count(),
            ],
            'verifications' => $alumnus->verificationRequests->sortByDesc('id')->values()->map(fn ($v) => [
                'status' => $v->status->value, 'method' => $v->method, 'at' => $v->created_at->format('j M Y'),
                'by' => $v->reviewer?->name, 'reason' => $v->decision_reason,
            ]),
            'consents' => $user->consents()->latest('id')->get(['consent_type', 'version', 'granted', 'created_at'])
                ->map(fn ($c) => ['type' => $c->consent_type, 'version' => $c->version, 'granted' => $c->granted, 'at' => $c->created_at->format('j M Y')]),
            'audit' => $request->user()->can(Permission::AuditView->value)
                ? AuditLog::where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('entity_type', 'AlumniProfile')->where('entity_id', $alumnus->id))
                    ->orWhere(fn ($q) => $q->where('entity_type', 'User')->where('entity_id', $user->id)))
                    ->latest('id')->limit(15)->get(['action', 'created_at', 'ip_address'])
                    ->map(fn ($l) => ['action' => $l->action, 'at' => $l->created_at->format('j M Y, H:i'), 'ip' => $l->ip_address])
                : null,
        ]);
    }

    /** CSV export (SRS 95): permission, recent password confirmation, size cap, audited. */
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        abort_unless($request->user()->can(Permission::AlumniExport->value), 403);
        $filters = $this->filtered($request);
        $query = $this->query($filters);

        if ((clone $query)->count() > self::EXPORT_LIMIT) {
            return back()->with('error', 'That selection is larger than '.number_format(self::EXPORT_LIMIT).' rows. Narrow the filters and try again.');
        }

        $this->audit->record('alumni.exported', 'alumni', null, null, ['filters' => $filters, 'rows' => (clone $query)->count()]);
        $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;

        return response()->streamDownload(function () use ($query, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Phone', 'Roll number', 'Programme', 'Admission', 'Graduation', 'Company', 'Designation', 'Industry', 'City', 'Country', 'Status']);
            $query->chunkById(500, function ($rows) use ($out, $safe) {
                foreach ($rows as $p) {
                    fputcsv($out, array_map($safe, [
                        $p->user->name, $p->user->email, $p->user->phone, $p->roll_number, $p->programme->code, $p->admission_year,
                        $p->graduation_year, $p->company, $p->designation, $p->industry, $p->city, $p->country, $p->verification_status->value,
                    ]));
                }
            });
            fclose($out);
        }, 'alumni-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function importPage(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::AlumniImport->value), 403);

        return Inertia::render('Admin/Alumni/Import', [
            'columns' => AlumniRecordImporter::COLUMNS,
            'required' => AlumniRecordImporter::REQUIRED,
            'programmeCodes' => Programme::orderBy('code')->pluck('code'),
            'preview' => $request->session()->get('import_preview'),
            'history' => Import::with('user:id,name')->latest()->limit(15)->get()->map(fn (Import $i) => [
                'id' => $i->id, 'file' => $i->original_name, 'status' => $i->status, 'by' => $i->user?->name,
                'total' => $i->total_rows, 'valid' => $i->valid_rows, 'created' => $i->created_rows, 'updated' => $i->updated_rows,
                'at' => $i->created_at->diffForHumans(), 'errors' => $i->status === 'failed' ? $i->errors : null,
            ]),
        ]);
    }

    public function importPreview(Request $request, AlumniRecordImporter $importer): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::AlumniImport->value), 403);
        // Content-sniffed MIME (not the client's claim) must be text/CSV.
        $file = $request->validate(['file' => ['required', 'file', 'max:5120', 'mimetypes:text/plain,text/csv,application/csv']])['file'];

        $result = $importer->preview($request->user(), $file);

        return redirect()->route('admin.alumni.import')->with('import_preview', [
            'id' => $result['import']->id,
            'file' => $result['import']->original_name,
            'total' => $result['import']->total_rows,
            'valid' => $result['import']->valid_rows,
            'new' => $result['new'],
            'updates' => $result['updates'],
            'errors' => $result['import']->errors,
            'sample' => $result['sample'],
        ]);
    }

    public function importConfirm(Request $request, Import $import): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::AlumniImport->value), 403);
        abort_unless($import->status === 'previewed' && $import->user_id === $request->user()->id && $import->valid_rows > 0, 409);

        $import->forceFill(['status' => 'queued'])->save();
        ImportAlumniRecords::dispatch($import);

        return redirect()->route('admin.alumni.import')->with('success', "Import queued: {$import->valid_rows} rows. Refresh to see progress.");
    }
}
