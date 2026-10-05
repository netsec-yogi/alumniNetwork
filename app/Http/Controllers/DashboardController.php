<?php

namespace App\Http\Controllers;

use App\Models\AlumniProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->alumniProfile?->load('programme:id,name');

        $completion = null;
        if ($profile) {
            $fields = ['company', 'designation', 'industry', 'city', 'country', 'bio', 'linkedin_url', 'specialization'];
            $filled = collect($fields)->filter(fn ($f) => filled($profile->{$f}))->count() + (filled($profile->interests) ? 1 : 0);
            $completion = (int) round($filled / (count($fields) + 1) * 100);
        }

        $latestRequest = $profile?->verificationRequests()->latest()->first(['id', 'status', 'decision_reason', 'created_at']);

        return Inertia::render('Dashboard', [
            'profile' => $profile ? [
                'programme' => $profile->programme->name,
                'graduation_year' => $profile->graduation_year,
                'verification_status' => $profile->verification_status->value,
                'rejection_reason' => $latestRequest?->status->value === 'rejected' ? $latestRequest->decision_reason : null,
                'completion' => $completion,
                'id' => $profile->id,
            ] : null,
            'canBrowseDirectory' => $user->can('viewAny', AlumniProfile::class),
            'alumniCount' => AlumniProfile::verified()->count(),
        ]);
    }
}
