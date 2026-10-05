<?php

namespace App\Http\Controllers;

use App\Models\AlumniProfile;
use App\Models\Connection;
use App\Models\User;
use App\Services\ConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Alumni Connect (SRS 24). */
class ConnectionController extends Controller
{
    public function __construct(private readonly ConnectionService $connections) {}

    public function index(Request $request): Response
    {
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['connections', 'received', 'sent', 'blocked'])]])['tab'] ?? 'connections';
        $me = $request->user();

        $card = fn (User $u, array $extra = []) => [
            'user_id' => $u->id,
            'name' => $u->alumniProfile?->displayName() ?? $u->name,
            'profile_id' => $u->alumniProfile?->isVerified() ? $u->alumniProfile->id : null,
            'subtitle' => $u->alumniProfile
                ? "{$u->alumniProfile->programme->name} · {$u->alumniProfile->graduation_year}"
                : $u->getRoleNames()->map(fn ($r) => ucfirst($r))->implode(', '),
            ...$extra,
        ];

        $with = ['alumniProfile.programme:id,name', 'roles:id,name'];

        $items = match ($tab) {
            'connections' => Connection::involving($me->id)->where('status', Connection::ACCEPTED)
                ->with(['requester' => fn ($q) => $q->with($with), 'addressee' => fn ($q) => $q->with($with)])
                ->latest('responded_at')->paginate(30)
                ->through(fn (Connection $c) => $card($c->requester_id === $me->id ? $c->addressee : $c->requester, [
                    'connection_id' => $c->id, 'since' => $c->responded_at?->diffForHumans(),
                ])),
            'received' => Connection::where('addressee_id', $me->id)->where('status', Connection::PENDING)
                ->with(['requester' => fn ($q) => $q->with($with)])->latest()->paginate(30)
                ->through(fn (Connection $c) => $card($c->requester, [
                    'connection_id' => $c->id, 'message' => $c->message, 'since' => $c->created_at->diffForHumans(),
                ])),
            'sent' => Connection::where('requester_id', $me->id)->where('status', Connection::PENDING)
                ->with(['addressee' => fn ($q) => $q->with($with)])->latest()->paginate(30)
                ->through(fn (Connection $c) => $card($c->addressee, [
                    'connection_id' => $c->id, 'since' => $c->created_at->diffForHumans(),
                ])),
            'blocked' => $me->blocks()->with($with)->paginate(30)->through(fn (User $u) => $card($u)),
        };

        return Inertia::render('Connections/Index', [
            'tab' => $tab,
            'items' => $items,
            'counts' => [
                'connections' => $this->connections->connectedIds($me->id)->count(),
                'received' => Connection::where('addressee_id', $me->id)->where('status', Connection::PENDING)->count(),
                'sent' => Connection::where('requester_id', $me->id)->where('status', Connection::PENDING)->count(),
            ],
            'suggestions' => $tab === 'connections' ? $this->connections->suggestions($me) : [],
        ]);
    }

    public function store(Request $request, AlumniProfile $profile): RedirectResponse
    {
        $this->authorize('interact', $profile);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:300']]);

        return $this->attempt(fn () => $this->connections->request($request->user(), $profile->user, $data['message'] ?? null), 'Request sent.');
    }

    public function accept(Request $request, Connection $connection): RedirectResponse
    {
        return $this->attempt(fn () => $this->connections->accept($request->user(), $connection), 'Connected.');
    }

    public function decline(Request $request, Connection $connection): RedirectResponse
    {
        return $this->attempt(fn () => $this->connections->decline($request->user(), $connection), 'Request declined.');
    }

    public function destroy(Request $request, Connection $connection): RedirectResponse
    {
        return $this->attempt(fn () => $this->connections->remove($request->user(), $connection), 'Removed.');
    }

    public function follow(Request $request, AlumniProfile $profile): RedirectResponse
    {
        $this->authorize('interact', $profile);

        return $this->attempt(fn () => $this->connections->follow($request->user(), $profile->user), "You're following {$profile->displayName()}.");
    }

    public function unfollow(Request $request, AlumniProfile $profile): RedirectResponse
    {
        $this->connections->unfollow($request->user(), $profile->user);

        return back();
    }

    public function block(Request $request, AlumniProfile $profile): RedirectResponse
    {
        abort_if($request->user()->id === $profile->user_id, 422);
        $this->connections->block($request->user(), $profile->user);

        return redirect()->route('directory')->with('success', 'Blocked. You won’t see each other in the directory.');
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        $this->connections->unblock($request->user(), $user);

        return back()->with('success', 'Unblocked.');
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
