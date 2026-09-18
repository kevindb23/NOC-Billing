<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\RouterVendorRegistry;

class StoreRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'vendor' => ['required', 'string', Rule::in(RouterVendorRegistry::identifiers())],
            'model' => ['nullable', 'string', 'max:100'],
            'management_endpoint' => ['nullable', 'string', 'max:190'],
            'preferred_transport' => ['nullable', 'string', 'in:ssh'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:unknown,active,inactive,offline'],
        ];
    }
}
