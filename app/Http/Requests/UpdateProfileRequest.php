<?php

namespace App\Http\Requests;

use App\Enums\Visibility;
use App\Models\AlumniProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->user()->alumniProfile;

        return $profile !== null && $this->user()->can('update', $profile);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $visibility = Rule::enum(Visibility::class);

        return [
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['female', 'male', 'non_binary', 'prefer_not_to_say'])],
            'bio' => ['nullable', 'string', 'max:2000'],
            'specialization' => ['nullable', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:150'],
            'industry' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            // https only: a javascript: or data: URL here would be stored XSS.
            'linkedin_url' => ['nullable', 'url:https', 'max:255', 'regex:#^https://([a-z]{2,3}\.)?linkedin\.com/#i'],
            'website_url' => ['nullable', 'url:https', 'max:255'],
            'interests' => ['array'],
            'interests.*' => ['string', Rule::in(array_keys(AlumniProfile::INTERESTS))],
            'visibility' => ['array:'.implode(',', array_keys(AlumniProfile::PRIVACY_FIELDS))],
            'visibility.*' => [$visibility],
        ];
    }

    public function messages(): array
    {
        return [
            'linkedin_url.regex' => 'Enter a linkedin.com profile address.',
            'linkedin_url.url' => 'Enter a full https:// address.',
            'website_url.url' => 'Enter a full https:// address.',
        ];
    }
}
