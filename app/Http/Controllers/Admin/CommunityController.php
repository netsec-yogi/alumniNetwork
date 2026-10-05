<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Create official communities and chapters, and appoint their admins (SRS 27-28). */
class CommunityController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Kinds this admin may manage: communities.moderate → communities, chapters.manage → chapters. */
    private function kinds(User $user): array
    {
        return array_keys(array_filter([
            Community::KIND_COMMUNITY => $user->can(Permission::CommunitiesModerate->value),
            Community::KIND_CHAPTER => $user->can(Permission::ChaptersManage->value) || $user->can(Permission::CommunitiesModerate->value),
        ]));
    }

    public function index(Request $request): Response
    {
        $kinds = $this->kinds($request->user());
        abort_if($kinds === [], 403);

        return Inertia::render('Admin/Communities/Index', [
            'groups' => Community::whereIn('kind', $kinds)
                ->withCount(['memberships as members_count' => fn ($q) => $q->where('status', CommunityMember::ACTIVE)])
                ->orderBy('kind')->orderBy('name')->paginate(30)
                ->through(fn (Community $c) => [
                    'id' => $c->id, 'slug' => $c->slug, 'name' => $c->name, 'kind' => $c->kind,
                    'category' => Community::CATEGORIES[$c->kind][$c->category] ?? $c->category,
                    'join_policy' => $c->join_policy, 'is_official' => $c->is_official, 'members_count' => $c->members_count,
                ]),
            'kinds' => $kinds,
            'categories' => Community::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kinds = $this->kinds($request->user());
        $data = $request->validate([
            'kind' => ['required', Rule::in($kinds)],
            'category' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'join_policy' => ['required', Rule::in([Community::OPEN, Community::APPROVAL])],
            'admin_email' => ['nullable', 'email', 'exists:users,email'],
        ]);
        abort_unless(array_key_exists($data['category'], Community::CATEGORIES[$data['kind']]), 422);

        $community = new Community([...$data, 'is_official' => true]);
        $community->forceFill(['created_by' => $request->user()->id])->save();

        if (! empty($data['admin_email'])) {
            $admin = User::where('email', strtolower($data['admin_email']))->firstOrFail();
            CommunityMember::firstOrNew(['community_id' => $community->id, 'user_id' => $admin->id])
                ->forceFill(['role' => CommunityMember::ADMIN, 'status' => CommunityMember::ACTIVE])->save();
        }

        $this->audit->record('community.created', 'communities', $community, null, ['name' => $community->name, 'admin' => $data['admin_email'] ?? null]);

        return back()->with('success', "{$community->name} created.");
    }

    public function destroy(Request $request, Community $community): RedirectResponse
    {
        abort_unless(in_array($community->kind, $this->kinds($request->user()), true), 403);
        $community->delete();
        $this->audit->record('community.archived', 'communities', $community);

        return back()->with('success', "{$community->name} archived.");
    }
}
