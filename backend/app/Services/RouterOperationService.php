<?php

namespace App\Services;

use App\Jobs\ExecuteRouterOperation;
use App\Models\Router;
use App\Models\RouterCredential;
use App\Models\RouterOperation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class RouterOperationService
{
    /** @var list<string> */
    private const MONITORING_OPERATIONS = [
        'test_connection',
        'get_system_info',
        'get_device_facts',
        'get_interfaces',
        'get_interface_status',
        'get_routes',
        'get_bgp_neighbors',
        'get_traffic_counters',
    ];

    /** @var list<string> */
    private const CONFIGURATION_OPERATIONS = [
        'validate_configuration',
        'preview_configuration',
        'apply_configuration',
        'commit_configuration',
        'rollback_configuration',
    ];

    public function __construct(private readonly RouterCredentialService $credentialService) {}

    /** @return list<string> */
    public static function operations(): array
    {
        return [...self::MONITORING_OPERATIONS, ...self::CONFIGURATION_OPERATIONS];
    }

    public static function isConfiguration(string $operation): bool
    {
        return in_array($operation, self::CONFIGURATION_OPERATIONS, true);
    }

    public static function permissionFor(string $operation): string
    {
        return self::isConfiguration($operation) ? 'routers.update' : 'routers.test';
    }

    /**
     * @param array{operation:string,parameters?:array<string,mixed>,correlation_id?:string} $data
     */
    public function createAndDispatch(User $user, Router $router, array $data): RouterOperation
    {
        $operation = (string) $data['operation'];
        $credential = $router->primaryCredential()->first();

        if ($credential === null || ! $this->credentialService->isConfigured($router, $credential)) {
            throw ValidationException::withMessages([
                'credential_profile' => ['A configured credential profile is required for this operation.'],
            ]);
        }

        if (! $this->supports($router, $operation)) {
            throw ValidationException::withMessages([
                'operation' => ['This operation is not supported by the selected router driver.'],
            ]);
        }

        $parameters = $data['parameters'] ?? [];
        $parameters['credential_profile_id'] = $credential->public_id;
        $operationRecord = RouterOperation::create([
            'router_id' => $router->getKey(),
            'requested_by' => $user->getAuthIdentifier(),
            'operation' => $operation,
            'driver' => $router->driver,
            'transport' => $router->preferred_transport,
            'parameters' => $parameters,
            'status' => RouterOperation::STATUS_QUEUED,
            'correlation_id' => (string) ($data['correlation_id'] ?? Str::uuid()),
        ]);

        ExecuteRouterOperation::dispatch($operationRecord->getKey());

        return $operationRecord->fresh();
    }

    public function supports(Router $router, string $operation): bool
    {
        $capabilities = is_array($router->capabilities) ? $router->capabilities : [];
        $aliases = match ($operation) {
            'test_connection' => ['test_connection', 'connection_test'],
            'get_system_info' => ['get_system_info', 'system_info'],
            default => [$operation],
        };

        return count(array_intersect($aliases, array_map('strval', $capabilities))) > 0;
    }

    /** @return array<string, mixed> */
    public function gatewayPayload(RouterOperation $operation): array
    {
        $router = $operation->router()->firstOrFail();
        $credential = $router->primaryCredential()->first();

        if ($credential === null || ! $this->credentialService->isConfigured($router, $credential)) {
            throw new NetworkAutomationException(
                'A configured credential profile is required for this operation.',
                'credential_missing',
                422,
            );
        }

        $metadata = is_array($credential->connection_metadata) ? $credential->connection_metadata : [];
        $device = array_filter([
            'router_id' => $router->public_id,
            'driver' => $operation->driver,
            'transport' => $operation->transport,
            'credential_version' => (int) $credential->version,
            'hostname' => $router->hostname,
            'management_ip' => $router->management_ip,
            'port' => $metadata['port'] ?? null,
            'vendor' => $router->vendor,
            'model' => $router->model,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $credentials = array_filter([
            'username' => $credential->username,
            'password' => $credential->password,
            'private_key' => $credential->private_key,
            'passphrase' => $credential->private_key_passphrase,
            'token' => $credential->api_token,
            'community' => $credential->snmp_community,
            'tls_ca_certificate' => $credential->tls_ca_certificate,
            'tls_certificate' => $credential->tls_client_certificate,
            'tls_private_key' => $credential->tls_client_key,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $parameters = is_array($operation->parameters) ? $operation->parameters : [];
        unset($parameters['credential_profile_id']);

        return [
            'operation' => $operation->operation,
            'device' => $device,
            'credentials' => $credentials,
            'parameters' => $parameters,
            'correlation_id' => $operation->correlation_id,
        ];
    }

    /** @return array<string, mixed> */
    public function resource(RouterOperation $operation): array
    {
        return [
            'public_id' => $operation->public_id,
            'router_id' => $operation->router?->public_id,
            'operation' => $operation->operation,
            'operation_type' => self::isConfiguration($operation->operation) ? 'configuration' : 'monitoring',
            'driver' => $operation->driver,
            'transport' => $operation->transport,
            'parameters' => $operation->parameters,
            'status' => $operation->status,
            'result' => $operation->result,
            'error_code' => $operation->error_code,
            'error_message' => $operation->error_message,
            'correlation_id' => $operation->correlation_id,
            'started_at' => $operation->started_at,
            'finished_at' => $operation->finished_at,
            'duration_ms' => $operation->duration_ms,
            'created_at' => $operation->created_at,
            'updated_at' => $operation->updated_at,
        ];
    }
}
