<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\RouterVendorRegistry;

class UpdateRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:190'],
            'vendor' => ['sometimes', 'string', Rule::in(RouterVendorRegistry::identifiers())],
            'model' => ['nullable', 'string', 'max:100'],
            'management_endpoint' => ['nullable', 'string', 'max:190'],
            'preferred_transport' => ['nullable', 'string', 'in:ssh'],
            'username' => ['sometimes', 'nullable', 'string', 'max:190'],
            'password' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:unknown,active,inactive,offline'],
        ];
    }
}
