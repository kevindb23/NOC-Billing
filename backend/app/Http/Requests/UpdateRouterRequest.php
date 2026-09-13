<?php

namespace App\Http\Requests;

use App\Models\Router;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'metadata' => ['sometimes', 'nullable', 'array', 'max:20'],
            'metadata.*' => ['nullable', 'string', 'max:190'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $metadata = $this->input('metadata');
            if (! is_array($metadata)) {
                return;
            }

            foreach (array_keys($metadata) as $key) {
                if (! is_string($key) || ! Router::isSafeMetadataKey($key)) {
                    $validator->errors()->add('metadata', 'Router metadata may contain only approved non-sensitive fields.');
                }
            }
        });
    }
}
