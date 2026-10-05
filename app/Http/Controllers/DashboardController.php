<?php

namespace App\Http\Controllers;

use App\Models\AlumniProfile;
use App\Models\Event;
use App\Models\JobReferralRequest;
use App\Models\MentorshipRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->alumniProfile?->load('programme:id,name');

        $completion = $profile?->completion();

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
            'myEvents' => Event::published()->upcoming()
                ->whereHas('registrations', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', ['confirmed', 'waitlisted']))
                ->orderBy('starts_at')->limit(3)->get()
                ->map(fn ($e) => ['slug' => $e->slug, 'title' => $e->title, 'starts_at' => $e->starts_at->format('D j M, g:i A')]),
            'pending' => [
                'mentoring' => MentorshipRequest::where('mentor_id', $user->id)->where('status', 'pending')->count(),
                'referrals' => JobReferralRequest::where('status', 'pending')->whereHas('job', fn ($q) => $q->where('posted_by', $user->id))->count(),
            ],
        ]);
    }
}
