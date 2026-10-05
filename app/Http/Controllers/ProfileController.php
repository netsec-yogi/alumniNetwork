<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\AlumniProfile;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->alumniProfile?->load('programme');

        return Inertia::render('Profile/Edit', [
            'account' => $user->only('name', 'email', 'phone'),
            'profile' => $profile ? [
                ...$profile->only([
                    'preferred_name', 'gender', 'bio', 'specialization', 'company', 'designation',
                    'industry', 'city', 'state', 'country', 'linkedin_url', 'website_url',
                    'roll_number', 'graduation_year', 'admission_year',
                ]),
                'programme' => $profile->programme->name,
                'interests' => $profile->interests ?? [],
                'visibility' => collect(AlumniProfile::PRIVACY_FIELDS)
                    ->map(fn ($default, $field) => $profile->visibilityOf($field)->value),
                'verification_status' => $profile->verification_status->value,
            ] : null,
            'interestOptions' => AlumniProfile::INTERESTS,
            'visibilityOptions' => collect(Visibility::cases())->map(fn (Visibility $v) => ['value' => $v->value, 'label' => $v->label()]),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->alumniProfile;
        $original = $profile->getAttributes();

        $profile->fill($request->validated())->save();

        $this->audit->recordChanges('profile.updated', 'alumni', $profile, $original);

        return back()->with('success', 'Profile saved.');
    }

    public function updateAccount(UpdateAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $original = $user->only('name', 'email', 'phone');
        $data = $request->safe()->only('name', 'email', 'phone');
        $emailChanged = $data['email'] !== $user->email;

        $user->fill($data);
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }
        $user->save();

        $this->audit->recordChanges('account.updated', 'auth', $user, $original);

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
        }

        return back()->with('success', 'Account details saved.');
    }
}
