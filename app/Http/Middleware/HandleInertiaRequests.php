<?php

namespace App\Http\Middleware;

use App\Models\Connection;
use App\Services\Content\Branding;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Shared with every page. Only what the UI needs to render navigation
     * and gate buttons -- the server re-checks every action regardless.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'features' => ['ai' => (bool) config('ai.enabled')],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                    'two_factor_required' => $user->requiresTwoFactor(),
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'can_access_admin' => $user->canAccessAdmin(),
                    'is_member' => $user->isCommunityMember(),
                    'verification_status' => $user->alumniProfile?->verification_status?->value,
                ] : null,
            ],
            // Badge counts; closures so partial reloads can skip them.
            'counts' => fn () => $user ? [
                'notifications' => $user->unreadNotifications()->count(),
                'messages' => app(MessagingService::class)->unreadCount($user),
                'connectionRequests' => Connection::where('addressee_id', $user->id)->where('status', 'pending')->count(),
            ] : null,
            // Published logos and name (cached until the next publish); the admin preview overrides it.
            'branding' => fn () => Arr::except(app(Branding::class)->forPages(), 'file_ids'),
            'flash' => fn () => [
                'status' => $request->session()->get('status'),
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Route table for the route() helper, sent once per page load instead of
     * as an inline script, which the CSP would have to allow.
     *
     * @return array<string, mixed>
     */
    public function shareOnce(Request $request): array
    {
        return [
            'ziggy' => fn () => (new Ziggy)->toArray(),
        ];
    }
}
