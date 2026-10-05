<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Report abuse (SRS 24-26). One report per reporter per item. */
class ReportController extends Controller
{
    /** Morph aliases that may be reported, with the policy ability required to see them. */
    public const REPORTABLE = ['alumni_profile', 'post', 'post_comment', 'job_posting'];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::REPORTABLE)],
            'id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(array_keys(Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $class = Relation::getMorphedModel($data['type']);
        abort_if($class === null, 404);
        $target = $class::findOrFail($data['id']);

        // You can only report what you are allowed to see.
        $this->authorize('view', $target);

        $key = [
            'reporter_id' => $request->user()->id,
            'reportable_type' => $data['type'],
            'reportable_id' => $target->getKey(),
        ];

        if (! Report::where($key)->exists()) {
            (new Report)->forceFill($key)->fill(['reason' => $data['reason'], 'details' => $data['details'] ?? null])->save();
        }

        return back()->with('success', 'Thanks — the moderators will review this.');
    }
}
