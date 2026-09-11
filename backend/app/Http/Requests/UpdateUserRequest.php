<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:190'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->route('publicId'), 'public_id')],
            'password' => ['sometimes', 'string', 'min:8'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'distinct', $this->organizationRoleRule()],
        ];
    }

    private function organizationRoleRule(): \Closure
    {
        $organization = $this->attributes->get('organization');

        return function (string $attribute, mixed $value, \Closure $fail) use ($organization): void {
            if (! $organization || ! \App\Models\Role::query()
                ->whereKey($value)
                ->where(function ($query) use ($organization): void {
                    $query->where('organization_id', $organization->id)->orWhereNull('organization_id');
                })->exists()) {
                $fail('The selected role is not valid for this organization.');
            }
        };
    }
}
