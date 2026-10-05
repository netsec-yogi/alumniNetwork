<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->user();
        $changingContact = (string) $this->input('email') !== $user->email
            || (string) $this->input('phone') !== (string) $user->phone;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            // SRS 77: changing email or mobile needs the current password.
            'current_password' => $changingContact ? ['required', 'current_password:web'] : ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }
}
