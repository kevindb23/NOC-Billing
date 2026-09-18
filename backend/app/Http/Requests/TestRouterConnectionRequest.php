<?php

namespace App\Http\Requests;

use App\Services\RouterVendorRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestRouterConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'vendor' => ['required', 'string', Rule::in(RouterVendorRegistry::identifiers())],
            'management_endpoint' => ['required', 'string', 'max:190'],
            'preferred_transport' => ['required', 'string', 'in:ssh'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:1000'],
        ];
    }
}
