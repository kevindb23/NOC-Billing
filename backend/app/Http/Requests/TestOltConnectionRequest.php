<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestOltConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'vendor' => ['required', 'string', Rule::in(['huawei', 'zte'])],
            'management_endpoint' => ['required', 'string', 'max:190'],
            'preferred_transport' => ['required', 'string', Rule::in(['ssh', 'telnet', 'api', 'netconf'])],
            'username' => ['required_if:preferred_transport,ssh', 'nullable', 'string', 'max:190'],
            'password' => ['required_if:preferred_transport,ssh', 'nullable', 'string', 'max:1000'],
        ];
    }
}
