<?php

namespace App\Http\Requests;

use App\Models\JobPosting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof JobPosting ? $this->user()->can('update', $job) : $this->user()->can('create', JobPosting::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(JobPosting::TYPES))],
            'title' => ['required', 'string', 'max:160'],
            'organization' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'work_mode' => ['required', Rule::in(array_keys(JobPosting::WORK_MODES))],
            'employment_type' => ['required', Rule::in(array_keys(JobPosting::EMPLOYMENT_TYPES))],
            'experience_min' => ['nullable', 'integer', 'min:0', 'max:40'],
            'experience_max' => ['nullable', 'integer', 'min:0', 'max:50', 'gte:experience_min'],
            'skills' => ['array', 'max:15'],
            'skills.*' => ['string', 'max:40'],
            'compensation' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'min:30', 'max:10000'],
            'apply_url' => ['required_without:apply_email', 'nullable', 'url:https', 'max:255'],
            'apply_email' => ['required_without:apply_url', 'nullable', 'email:rfc', 'max:255'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'referral_available' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'apply_url.required_without' => 'Give an application link or an email address.',
            'apply_email.required_without' => 'Give an application link or an email address.',
        ];
    }
}
