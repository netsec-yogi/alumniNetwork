<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Moderatable;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AlumniProfile;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Abuse-report queue. Each report type is routed to the permission that
 * owns that kind of content, so a community moderator sees post reports
 * but not reports about people (SRS 112).
 */
class ModerationController extends Controller
{
    public const TYPE_PERMISSIONS = [
        'post' => Permission::CommunitiesModerate,
        'post_comment' => Permission::CommunitiesModerate,
        'job_posting' => Permission::JobsModerate,
        'alumni_profile' => Permission::UsersManage,
        // Moderators see only the reported message, never the conversation.
        'message' => Permission::UsersManage,
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @return list<string> */
    private function typesFor(User $user): array
    {
        return array_keys(array_filter(self::TYPE_PERMISSIONS, fn (Permission $p) => $user->can($p->value)));
    }

    public function index(Request $request): Response
    {
        $types = $this->typesFor($request->user());
        abort_if($types === [], 403);

        $status = $request->validate(['status' => ['nullable', Rule::in([Report::OPEN, Report::ACTIONED, Report::DISMISSED])]])['status'] ?? Report::OPEN;

        $reports = Report::query()
            ->whereIn('reportable_type', $types)
            ->where('status', $status)
            ->with([
                'reporter:id,name',
                'reviewer:id,name',
                'reportable' => fn (MorphTo $m) => $m->morphWith([AlumniProfile::class => ['user:id,name']]),
            ])
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Report $r) => [
                'id' => $r->id,
                'type' => $r->reportable_type,
                'summary' => $this->summarise($r),
                'url' => $this->linkTo($r),
                'reason' => Report::REASONS[$r->reason] ?? $r->reason,
                'details' => $r->details,
                'reporter' => $r->reporter?->name,
                'at' => $r->created_at->diffForHumans(),
                'can_remove' => $r->reportable instanceof Moderatable,
                'resolution' => $r->resolution,
                'reviewer' => $r->reviewer?->name,
            ]);

        return Inertia::render('Admin/Moderation/Index', [
            'reports' => $reports,
            'status' => $status,
        ]);
    }

    public function resolve(Request $request, Report $report): RedirectResponse
    {
        abort_unless(in_array($report->reportable_type, $this->typesFor($request->user()), true), 403);
        abort_if($report->status !== Report::OPEN, 409);

        $data = $request->validate([
            'action' => ['required', Rule::in(['dismiss', 'remove'])],
            'resolution' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $target = $report->reportable;

        if ($data['action'] === 'remove') {
            abort_unless($target instanceof Moderatable, 422);
            $target->removeByModerator($request->user(), $data['resolution']);
        }

        // Resolve every open report on the same item together.
        Report::where('reportable_type', $report->reportable_type)
            ->where('reportable_id', $report->reportable_id)
            ->where('status', Report::OPEN)
            ->update([
                'status' => $data['action'] === 'remove' ? Report::ACTIONED : Report::DISMISSED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'resolution' => $data['resolution'],
            ]);

        $this->audit->record("moderation.{$data['action']}", 'moderation', $report, null, [
            'reportable' => "{$report->reportable_type}#{$report->reportable_id}",
            'resolution' => $data['resolution'],
        ]);

        return back()->with('success', $data['action'] === 'remove' ? 'Content removed.' : 'Report dismissed.');
    }

    private function summarise(Report $r): string
    {
        $target = $r->reportable;

        return match (true) {
            $target === null => '(deleted)',
            $target instanceof Moderatable => $target->moderationSummary(),
            $target instanceof AlumniProfile => "Profile of {$target->user->name}",
            default => Str::headline($r->reportable_type).' #'.$r->reportable_id,
        };
    }

    private function linkTo(Report $r): ?string
    {
        return match ($r->reportable_type) {
            'alumni_profile' => route('alumni.show', $r->reportable_id),
            'job_posting' => route('jobs.show', $r->reportable_id),
            'post' => route('posts.show', $r->reportable_id),
            'post_comment' => $r->reportable ? route('posts.show', $r->reportable->post_id) : null,
            'message' => null, // never link into a private conversation
            default => null,
        };
    }
}
