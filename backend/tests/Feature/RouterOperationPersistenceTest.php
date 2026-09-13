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
