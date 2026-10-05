<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\RoleAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $assignable = app(RoleAssignment::class)->assignableBy($this->user())->pluck('name')->all();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($assignable)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }
}
