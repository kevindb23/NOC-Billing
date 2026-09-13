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
        $rules = [
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
            'preferred_transport' => ['sometimes', 'string', Rule::in(RouterCredentialRequest::TRANSPORTS)],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance', 'unknown'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'metadata' => ['sometimes', 'nullable', 'array', 'max:20'],
            'metadata.*' => ['nullable', 'string', 'max:190'],
            'credential_profile' => ['sometimes', 'array:name,port,username,password,private_key,private_key_passphrase,known_hosts,tls_ca_certificate,tls_client_certificate,tls_client_key,api_base_url,auth_mode,api_token,snmp_version,snmp_community,snmp_security'],
        ];

        $transport = (string) $this->input('preferred_transport');
        if ($transport === '') {
            $transport = (string) (Router::query()
                ->where('public_id', (string) $this->route('publicId'))
                ->value('preferred_transport') ?? 'api');
        }

        foreach (RouterCredentialRequest::rulesForTransport($transport) as $field => $fieldRules) {
            $rules['credential_profile.'.$field] = array_values(array_filter(
                array_merge(['sometimes', 'nullable'], $fieldRules),
                static fn (mixed $rule): bool => $rule !== 'required' && $rule !== 'min:1',
            ));
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

            $publicId = (string) $this->route('publicId');
            $router = Router::query()->where('public_id', $publicId)->with('primaryCredential')->first();
            if ($router === null) {
                return;
            }

            $transport = (string) $this->input('preferred_transport', $router->preferred_transport);
            $transportChanged = $transport !== $router->preferred_transport;
            $profile = $this->input('credential_profile');
            if ($transportChanged && ! is_array($profile)) {
                $this->addRequiredCredentialErrors($validator, $transport);
                return;
            }

            if (! is_array($profile)) {
                return;
            }

            foreach ($this->requiredFieldsFor($transport, $transportChanged, $router->primaryCredential) as $field) {
                if (! filled($profile[$field] ?? null)) {
                    $validator->errors()->add('credential_profile.'.$field, 'This credential is required for the selected transport.');
                }
            }
        });
    }

    private function addRequiredCredentialErrors(Validator $validator, string $transport): void
    {
        foreach ($this->requiredFieldsFor($transport, true, null) as $field) {
            $validator->errors()->add('credential_profile.'.$field, 'This credential is required for the selected transport.');
        }
    }

    /** @return list<string> */
    private function requiredFieldsFor(string $transport, bool $transportChanged, mixed $credential): array
    {
        if ($transport === 'ssh' || $transport === 'netconf') {
            if ($transportChanged || $credential === null) {
                return ['username', 'password'];
            }

            $required = [];
            if (! filled($credential->username)) {
                $required[] = 'username';
            }
            if (! filled($credential->password)) {
                $required[] = 'password';
            }

            return $required;
        }

        if ($transport === 'api' && $transportChanged) {
            return ['api_token'];
        }

        if ($transport === 'snmp' && $transportChanged) {
            return ['snmp_community'];
        }

        return [];
    }
}
