<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RouterCredentialRequest extends FormRequest
{
    public const TRANSPORTS = ['api', 'ssh', 'netconf', 'snmp'];

    /** @return array<string, array<int, mixed>> */
    public static function rulesForTransport(string $transport): array
    {
        $common = [
            'name' => ['required', 'string', 'max:150'],
            'port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
        ];

        return match ($transport) {
            'ssh' => $common + [
                'username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:1', 'max:10000'],
                'private_key' => ['sometimes', 'nullable', 'string', 'max:100000'],
                'private_key_passphrase' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'known_hosts' => ['sometimes', 'nullable', 'string', 'max:100000'],
            ],
            'netconf' => $common + [
                'username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:1', 'max:10000'],
                'tls_ca_certificate' => ['sometimes', 'nullable', 'string', 'max:100000'],
                'tls_client_certificate' => ['sometimes', 'nullable', 'string', 'max:100000'],
                'tls_client_key' => ['sometimes', 'nullable', 'string', 'max:100000'],
            ],
            'api' => $common + [
                'api_base_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
                'auth_mode' => ['sometimes', 'nullable', 'string', Rule::in(['token'])],
                'api_token' => ['required', 'string', 'min:1', 'max:10000'],
            ],
            'snmp' => $common + [
                'snmp_version' => ['sometimes', 'nullable', 'string', Rule::in(['v1', 'v2c', 'v3'])],
                'snmp_community' => ['required', 'string', 'min:1', 'max:10000'],
                'snmp_security' => ['sometimes', 'nullable', 'array'],
            ],
            default => [],
        };
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $transport = (string) $this->input('preferred_transport', 'api');

        return self::rulesForTransport($transport);
    }
}
