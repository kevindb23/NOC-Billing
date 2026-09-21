<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOltRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:190'],
            'vendor' => ['sometimes', 'string', Rule::in(['huawei', 'zte', 'hsgq'])],
            'model' => ['nullable', 'string', 'max:100'],
            'management_endpoint' => ['nullable', 'string', 'max:190'],
            'preferred_transport' => ['sometimes', 'string', 'in:ssh,telnet,api,netconf'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:unknown,active,inactive'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
