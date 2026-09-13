<?php

namespace App\Http\Requests;

use App\Models\Router;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('routers', 'name')],
            'hostname' => ['nullable', 'string', 'max:190'],
            'management_ip' => ['nullable', 'ip'],
            'vendor' => ['required', 'string', Rule::in(['mikrotik', 'juniper', 'cisco', 'linux_frr', 'other'])],
            'model' => ['nullable', 'string', 'max:100'],
            'software_version' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'driver' => ['required', 'string', Rule::in([
                'mikrotik_router',
                'juniper_router',
                'cisco_router',
                'linux_frr_router',
            ])],
            'preferred_transport' => ['required', 'string', Rule::in(['api', 'ssh', 'netconf', 'snmp', 'mock'])],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'maintenance', 'unknown'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array', 'max:20'],
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
