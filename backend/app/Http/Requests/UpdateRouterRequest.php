<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('routers', 'name')->ignore($this->route('publicId'), 'public_id'),
            ],
            'hostname' => ['sometimes', 'nullable', 'string', 'max:190'],
            'management_ip' => ['sometimes', 'nullable', 'ip'],
            'vendor' => ['sometimes', 'string', Rule::in(['mikrotik', 'juniper', 'cisco', 'linux_frr', 'other'])],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'software_version' => ['sometimes', 'nullable', 'string', 'max:100'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'driver' => ['sometimes', 'string', Rule::in([
                'mikrotik_router',
                'juniper_router',
                'cisco_router',
                'linux_frr_router',
            ])],
            'preferred_transport' => ['sometimes', 'string', Rule::in(['api', 'ssh', 'netconf', 'snmp', 'mock'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance', 'unknown'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
