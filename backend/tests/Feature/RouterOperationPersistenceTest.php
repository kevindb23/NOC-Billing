<?php

namespace Tests\Feature;

use App\Models\Router;
use App\Models\RouterOperation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RouterOperationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_persists_normalized_lifecycle_fields_and_relationships(): void
    {
        $router = Router::create([
            'name' => 'Operation persistence router',
            'driver' => 'cisco_router',
        ]);
        $user = User::factory()->create();

        $operation = RouterOperation::create([
            'router_id' => $router->id,
            'requested_by' => $user->id,
            'operation' => 'get_interfaces',
            'driver' => 'cisco_router',
            'transport' => 'ssh',
            'parameters' => ['interface' => 'GigabitEthernet0/1'],
            'status' => 'succeeded',
            'result' => ['interfaces' => [['name' => 'GigabitEthernet0/1', 'state' => 'up']]],
            'error_code' => null,
            'error_message' => null,
            'correlation_id' => 'router-operation-correlation-1',
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
            'duration_ms' => 843,
        ]);

        $fresh = $operation->fresh();

        $this->assertSame('router-operation-correlation-1', $fresh->correlation_id);
        $this->assertSame(['interface' => 'GigabitEthernet0/1'], $fresh->parameters);
        $this->assertSame(['interfaces' => [['name' => 'GigabitEthernet0/1', 'state' => 'up']]], $fresh->result);
        $this->assertSame($router->id, $fresh->router->id);
        $this->assertSame($user->id, $fresh->requester->id);
        $this->assertNotContains('organization_id', Schema::getColumnListing('router_operations'));
    }

    public function test_operation_snapshots_do_not_expose_credentials(): void
    {
        $router = Router::create([
            'name' => 'Operation redaction router',
            'driver' => 'juniper_router',
        ]);

        $operation = RouterOperation::create([
            'router_id' => $router->id,
            'operation' => 'get_system_info',
            'driver' => 'juniper_router',
            'transport' => 'netconf',
            'parameters' => [
                'credential_profile_id' => '01J00000000000000000000000',
                'username' => 'automation-user',
                'password' => 'router-password-value',
            ],
            'status' => 'failed',
            'result' => ['message' => 'Device did not respond', 'credentials' => ['token' => 'api-token-value']],
            'error_code' => 'connection_failed',
            'error_message' => 'The device connection failed with password router-password-value.',
            'correlation_id' => 'router-operation-correlation-2',
            'duration_ms' => 1200,
        ]);

        $serialized = $operation->fresh()->toJson();

        $this->assertStringNotContainsString('router-password-value', $serialized);
        $this->assertStringNotContainsString('api-token-value', $serialized);
        $this->assertStringContainsString('[REDACTED]', $serialized);
    }

    public function test_operation_snapshot_redaction_covers_all_credential_material_and_quoted_values(): void
    {
        $router = Router::create([
            'name' => 'Comprehensive operation redaction router',
            'driver' => 'mikrotik_router',
        ]);

        $secrets = [
            'snapshot-username-value',
            'snapshot-user-value',
            'snapshot-login-value',
            'snapshot-password-value',
            'snapshot-token-value',
            'snapshot-community-value',
            'snapshot-private-key-value',
            'quoted username value',
            'quoted password value',
            'quoted token value',
            'quoted community value',
            'quoted private key value',
        ];

        $operation = RouterOperation::create([
            'router_id' => $router->id,
            'operation' => 'get_system_info',
            'driver' => 'mikrotik_router',
            'transport' => 'api',
            'parameters' => [
                'credential_profile_id' => '01J00000000000000000000000',
                'username' => 'snapshot-username-value',
                'user' => 'snapshot-user-value',
                'login' => 'snapshot-login-value',
                'password' => 'snapshot-password-value',
                'token' => 'snapshot-token-value',
                'community' => 'snapshot-community-value',
                'private-key' => 'snapshot-private-key-value',
                'nested' => [
                    'apiKey' => 'snapshot-token-value',
                    'message' => 'username="quoted username value" password="quoted password value"',
                ],
                'json_message' => '{"username":"json secret","password":"json pass"}',
                'prose' => 'The user logged in successfully.',
            ],
            'status' => 'failed',
            'result' => [
                'details' => 'token: "quoted token value" community: "quoted community value"',
                'credentials' => ['private key' => 'quoted private key value'],
            ],
            'error_code' => 'connection_failed',
            'error_message' => 'login: quoted login value username="quoted username value" password=multi word password value token="quoted token value" community: multi word community value private-key="quoted private key value" correlation_id=router-operation-correlation-redaction',
            'correlation_id' => 'router-operation-correlation-redaction',
        ]);

        $serialized = $operation->fresh()->toJson();

        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }

        $this->assertStringContainsString('credential_profile_id', $serialized);
        $this->assertStringContainsString('01J00000000000000000000000', $serialized);
        $this->assertStringContainsString('router-operation-correlation-redaction', $serialized);
        $this->assertStringContainsString('The user logged in successfully.', $serialized);
        $this->assertStringNotContainsString('json secret', $serialized);
        $this->assertStringNotContainsString('json pass', $serialized);
    }

    public function test_router_deletion_is_restricted_when_operation_history_exists(): void
    {
        $router = Router::create([
            'name' => 'Operation retention router',
            'driver' => 'linux_frr_router',
        ]);

        RouterOperation::create([
            'router_id' => $router->id,
            'operation' => 'get_system_info',
            'driver' => 'linux_frr_router',
            'transport' => 'ssh',
            'parameters' => [],
            'status' => 'succeeded',
            'correlation_id' => 'router-operation-correlation-3',
        ]);

        $this->expectException(QueryException::class);

        $router->forceDelete();
    }
}
