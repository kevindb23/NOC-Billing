<?php

namespace App\Services;

use App\Models\Router;
use App\Models\RouterCredential;

class RouterCredentialService
{
    /** @var list<string> */
    private const CONNECTION_METADATA_FIELDS = ['port', 'api_base_url', 'auth_mode', 'snmp_version'];

    /** @var list<string> */
    private const SECRET_FIELDS = [
        'username',
        'password',
        'private_key',
        'private_key_passphrase',
        'api_token',
        'snmp_community',
        'snmp_security',
        'known_hosts',
        'tls_ca_certificate',
        'tls_client_certificate',
        'tls_client_key',
    ];

    /** @param array<string, mixed> $validated */
    public function storeOrReplace(Router $router, array $validated): RouterCredential
    {
        $credential = $router->credentials()->where('is_primary', true)->first();
        $isNew = $credential === null;
        $secretChanged = $isNew;

        if ($credential === null) {
            $credential = new RouterCredential([
                'is_primary' => true,
                'version' => 1,
            ]);
            $credential->router()->associate($router);
        }

        $credential->name = $validated['name'] ?? $credential->name ?? 'primary';

        foreach (self::SECRET_FIELDS as $field) {
            if (! array_key_exists($field, $validated) || $validated[$field] === null || $validated[$field] === '') {
                continue;
            }

            if (! $isNew && $credential->{$field} !== $validated[$field]) {
                $secretChanged = true;
            }

            $credential->{$field} = $validated[$field];
        }

        if ($secretChanged && ! $isNew) {
            $credential->version = ((int) $credential->version) + 1;
        }

        $credential->auth_type = $this->authType($credential);
        $credential->connection_metadata = $this->connectionMetadata(
            $router,
            $validated,
            is_array($credential->connection_metadata) ? $credential->connection_metadata : [],
        );
        $credential->save();

        return $credential->fresh();
    }

    /** @return array<string, mixed> */
    public function metadata(RouterCredential $credential): array
    {
        return $credential->toResourceArray();
    }

    public function isConfigured(Router $router, ?RouterCredential $credential = null): bool
    {
        $credential ??= $router->credentials()->where('is_primary', true)->first();
        if ($credential === null) {
            return false;
        }

        return match ($router->preferred_transport) {
            'ssh', 'netconf' => filled($credential->username) && filled($credential->password),
            'api' => filled($credential->api_token),
            'snmp' => filled($credential->snmp_community),
            default => false,
        };
    }

    private function authType(RouterCredential $credential): string
    {
        $types = [];
        if (filled($credential->username) || filled($credential->password)) {
            $types[] = 'password';
        }
        if (filled($credential->private_key) || filled($credential->tls_client_key)) {
            $types[] = 'private_key';
        }
        if (filled($credential->api_token)) {
            $types[] = 'token';
        }
        if (filled($credential->snmp_community) || filled($credential->snmp_security)) {
            $types[] = 'community';
        }

        return count($types) > 1 ? 'mixed' : ($types[0] ?? 'mixed');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existing
     */
    private function connectionMetadata(Router $router, array $validated, array $existing): array
    {
        $transport = (string) $router->preferred_transport;
        $metadata = $this->transportChanged($router) ? [] : $existing;
        $metadata = array_intersect_key($metadata, array_flip($this->metadataFieldsForTransport($transport)));

        if (in_array('port', $this->metadataFieldsForTransport($transport), true) && array_key_exists('port', $validated)) {
            if ($validated['port'] === null || $validated['port'] === '') {
                unset($metadata['port']);
            } else {
                $metadata['port'] = (int) $validated['port'];
            }
        }

        if (! array_key_exists('port', $metadata)) {
            $metadata['port'] = match ($transport) {
                'ssh' => 22,
                'netconf' => 830,
                'snmp' => 161,
                default => null,
            };
            if ($metadata['port'] === null) {
                unset($metadata['port']);
            }
        }

        foreach (array_diff($this->metadataFieldsForTransport($transport), ['port']) as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($validated[$field] === null || $validated[$field] === '') {
                unset($metadata[$field]);
            } else {
                $metadata[$field] = $validated[$field];
            }
        }

        return $metadata;
    }

    private function transportChanged(Router $router): bool
    {
        $original = $router->getOriginal('preferred_transport');

        return $original !== null && $original !== $router->preferred_transport;
    }

    /** @return list<string> */
    private function metadataFieldsForTransport(string $transport): array
    {
        return match ($transport) {
            'api' => ['port', 'api_base_url', 'auth_mode'],
            'ssh', 'netconf' => ['port'],
            'snmp' => ['port', 'snmp_version'],
            default => self::CONNECTION_METADATA_FIELDS,
        };
    }
}
