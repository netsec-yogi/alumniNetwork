<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Programme;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Departments and programmes (SRS 97). */
class ProgrammeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::AlumniUpdate->value), 403);

        return Inertia::render('Admin/Programmes/Index', [
            'departments' => Department::orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'programmes' => Programme::with('department:id,code')->withCount('alumniProfiles')->orderBy('name')->get()
                ->map(fn (Programme $p) => [...$p->only(['id', 'code', 'name', 'degree', 'duration_years', 'department_id', 'is_active']), 'department' => $p->department?->code, 'alumni' => $p->alumni_profiles_count]),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::AlumniUpdate->value), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $department = Department::create(['code' => strtoupper($data['code']), 'name' => $data['name']]);
        $this->audit->record('department.created', 'settings', $department, null, $data);

        return back()->with('success', 'Department added.');
    }

    public function save(Request $request, ?Programme $programme = null): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::AlumniUpdate->value), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('programmes', 'code')->ignore($programme?->id)],
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['required', 'string', 'max:50'],
            'duration_years' => ['required', 'integer', 'min:1', 'max:7'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);

        if ($programme) {
            $original = $programme->getAttributes();
            $programme->update($data);
            $this->audit->recordChanges('programme.updated', 'settings', $programme, $original);
        } else {
            $programme = Programme::create($data);
            $this->audit->record('programme.created', 'settings', $programme, null, $data);
        }

        return back()->with('success', 'Programme saved.');
    }
}
