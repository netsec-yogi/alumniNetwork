<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\AlumniProfile;
use App\Models\EngagementActivity;
use App\Models\StoredFile;
use App\Services\AuditLogger;
use App\Services\EngagementRecorder;
use App\Services\MediaSettings;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
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
        $profile = $user->alumniProfile?->load('programme', 'photo');

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
                'photo_url' => $profile->photo?->url(true),
                'photo_limit_kb' => app(MediaSettings::class)->limit('profile_photo_kb'),
            ] : null,
            'interestOptions' => AlumniProfile::INTERESTS,
            'emailOptIn' => CommunicationPreferenceController::current($user),
            'visibilityOptions' => collect(Visibility::cases())->map(fn (Visibility $v) => ['value' => $v->value, 'label' => $v->label()]),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->alumniProfile;
        $original = $profile->getAttributes();

        $profile->fill($request->validated())->save();

        $this->audit->recordChanges('profile.updated', 'alumni', $profile, $original);

        if ($profile->completion() >= 80) {
            app(EngagementRecorder::class)->record($profile, 'PROFILE_COMPLETED', EngagementActivity::MODE_COMMUNICATION);
        }

        return back()->with('success', 'Profile saved.');
    }

    public function updatePhoto(Request $request, FileUploadService $uploads): RedirectResponse
    {
        $profile = $request->user()->alumniProfile;
        abort_if($profile === null, 404);
        $request->validate(['photo' => ['required', 'file', 'max:'.config('security.uploads.max_image_kb')]]);

        try {
            // Optimised to the admin-configured limit (default 200 KB); members-only unless the
            // alumnus is publicly featured (PublicMedia), never public by default.
            $file = $uploads->storeOptimizedImage($request->file('photo'), $request->user(), 'profile_photo', StoredFile::MEMBERS, app(MediaSettings::class)->limit('profile_photo_kb'), 'Profile photo', 800, 256);
        } catch (UploadRejected $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }

        $old = $profile->photo_file_id ? StoredFile::find($profile->photo_file_id) : null;
        $profile->forceFill(['photo_file_id' => $file->id])->save();
        $file->attachable()->associate($profile)->save();
        $old?->forceDelete();

        $this->audit->record('profile.photo_updated', 'alumni', $profile);

        return back()->with('success', 'Photo updated.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $profile = $request->user()->alumniProfile;
        $old = $profile?->photo_file_id ? StoredFile::find($profile->photo_file_id) : null;
        $profile?->forceFill(['photo_file_id' => null])->save();
        $old?->forceDelete();

        return back()->with('success', 'Photo removed.');
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
