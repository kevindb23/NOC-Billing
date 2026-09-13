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
        $rules = [
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
            'preferred_transport' => ['required', 'string', Rule::in(RouterCredentialRequest::TRANSPORTS)],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'maintenance', 'unknown'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array', 'max:20'],
            'metadata.*' => ['nullable', 'string', 'max:190'],
            'credential_profile' => ['sometimes', 'array:name,port,username,password,private_key,private_key_passphrase,known_hosts,tls_ca_certificate,tls_client_certificate,tls_client_key,api_base_url,auth_mode,api_token,snmp_version,snmp_community,snmp_security'],
        ];

        $transport = (string) $this->input('preferred_transport', 'api');
        foreach (RouterCredentialRequest::rulesForTransport($transport) as $field => $fieldRules) {
            if (in_array($transport, ['api', 'snmp'], true)) {
                $fieldRules = array_map(
                    static fn (mixed $rule): mixed => $rule === 'required' ? 'required_with:credential_profile' : $rule,
                    $fieldRules,
                );
            }
            $rules['credential_profile.'.$field] = $fieldRules;
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $metadata = $this->input('metadata');
            if (is_array($metadata)) {
                foreach (array_keys($metadata) as $key) {
                    if (! is_string($key) || ! Router::isSafeMetadataKey($key)) {
                        $validator->errors()->add('metadata', 'Router metadata may contain only approved non-sensitive fields.');
                    }
                }
            }

            $transport = (string) $this->input('preferred_transport');
            $profile = $this->input('credential_profile');
            if (in_array($transport, ['ssh', 'netconf'], true) && ! is_array($profile)) {
                $validator->errors()->add('credential_profile.username', 'A username is required for this transport.');
                $validator->errors()->add('credential_profile.password', 'A password is required for this transport.');
            }
        });
    }
}
