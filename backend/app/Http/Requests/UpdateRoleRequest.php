<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->attributes->get('organization');
        $roleId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:190',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('organization_id', $organization?->id))
                    ->ignore($roleId),
            ],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }
}
