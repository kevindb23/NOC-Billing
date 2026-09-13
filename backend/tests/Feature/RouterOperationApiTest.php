<?php

namespace Tests\Feature;

use App\Jobs\ExecuteRouterOperation;
use App\Http\Middleware\ResolveOrganization;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Router;
use App\Models\RouterCredential;
use App\Models\RouterOperation;
use App\Models\User;
use App\Services\RouterCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouterOperationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.guards.sanctum' => ['driver' => 'session', 'provider' => 'users']]);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    }

    public function test_authorized_monitoring_operation_is_queued_with_a_correlation_id_and_no_secret_snapshot(): void
    {
        [$actor, $router] = $this->routerFixture(['routers.test', 'routers.view']);
        Queue::fake();
        Sanctum::actingAs($actor);

        $response = $this->withHeader('X-Request-Id', 'router-monitor-1')->postJson(
            '/api/v1/routers/'.$router->public_id.'/operations',
            ['operation' => 'get_system_info', 'parameters' => []],
        );

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.operation_type', 'monitoring')
            ->assertJsonPath('data.correlation_id', 'router-monitor-1')
            ->assertJsonMissing(['password' => 'router-password']);

        $operation = RouterOperation::query()->firstOrFail();
        $this->assertSame($router->primaryCredential->public_id, $operation->parameters['credential_profile_id']);
        $this->assertSame($router->primaryCredential->public_id, $operation->credential_profile_id);
        $this->assertSame($router->primaryCredential->version, $operation->credential_version);
        $this->assertStringNotContainsString('router-password', $response->getContent());
        Queue::assertPushed(ExecuteRouterOperation::class, fn (ExecuteRouterOperation $job): bool => $job->operationId === $operation->id);
    }

    public function test_operation_routes_use_global_permissions_without_an_organization_context(): void
    {
        [$actor, $router] = $this->routerFixture(['routers.monitor']);
        Queue::fake();
        Sanctum::actingAs($actor);

        $this->withoutMiddleware(ResolveOrganization::class)
            ->postJson('/api/v1/routers/'.$router->public_id.'/operations', [
                'operation' => 'get_system_info',
                'parameters' => [],
            ])
            ->assertAccepted();
    }

    public function test_specific_configuration_permissions_are_not_interchangeable(): void
    {
        [$actor, $router] = $this->routerFixture(['routers.configuration.preview']);
        Queue::fake();
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/routers/'.$router->public_id.'/operations', [
            'operation' => 'preview_configuration',
            'parameters' => [],
        ])->assertAccepted();

        $this->postJson('/api/v1/routers/'.$router->public_id.'/operations', [
            'operation' => 'apply_configuration',
            'parameters' => [],
        ])->assertForbidden();
    }

    public function test_compatibility_routes_reject_an_unsafe_request_id(): void
    {
        [$actor, $router] = $this->routerFixture(['routers.test']);
        Sanctum::actingAs($actor);

        $this->withHeader('X-Request-Id', str_repeat('a', 129))
            ->postJson('/api/v1/routers/'.$router->public_id.'/connection-test')
            ->assertUnprocessable();
    }

    public function test_dispatch_failure_rolls_back_the_queued_operation(): void
    {
        [$actor, $router] = $this->routerFixture(['routers.monitor']);
        $this->mock(\Illuminate\Contracts\Bus\Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('queue unavailable'));
        });

        try {
            app(\App\Services\RouterOperationService::class)->createAndDispatch($actor, $router, [
                'operation' => 'get_system_info',
                'parameters' => [],
            ]);
            $this->fail('The dispatch failure should be raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('queue unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('router_operations', 0);
    }

    public function test_configuration_operation_uses_the_update_permission_hook(): void
    {
        [$actor, $router] = $this->routerFixture(['routers.update', 'routers.view']);
        Queue::fake();
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/routers/'.$router->public_id.'/operations', [
            'operation' => 'apply_configuration',
            'parameters' => ['configuration' => ['interface' => 'ge-0/0/0', 'enabled' => true]],
        ])->assertStatus(202)
            ->assertJsonPath('data.operation_type', 'configuration');
    }

    public function test_monitoring_permission_cannot_submit_configuration_operation(): void
    {
        [$actor, $router] = $this->routerFixture(['routers.test', 'routers.view']);
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/routers/'.$router->public_id.'/operations', [
            'operation' => 'apply_configuration',
            'parameters' => ['configuration' => ['interface' => 'ge-0/0/0', 'enabled' => true]],
        ])->assertForbidden();
    }

    public function test_operation_history_can_be_listed_and_shown_without_credentials(): void
    {
        [$actor, $router] = $this->routerFixture(['routers.view']);
        Sanctum::actingAs($actor);
        $operation = RouterOperation::create([
            'router_id' => $router->id,
            'operation' => 'get_interfaces',
            'driver' => $router->driver,
            'transport' => $router->preferred_transport,
            'parameters' => ['credential_profile_id' => '01J00000000000000000000001'],
            'result' => ['status' => 'succeeded', 'details' => ['password' => 'router-password']],
            'status' => RouterOperation::STATUS_SUCCEEDED,
            'correlation_id' => 'router-history-1',
        ]);

        $this->getJson('/api/v1/routers/'.$router->public_id.'/operations')
            ->assertOk()
            ->assertJsonPath('data.data.0.correlation_id', 'router-history-1')
            ->assertJsonMissing(['router-password']);
        $this->getJson('/api/v1/routers/'.$router->public_id.'/operations/'.$operation->public_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonMissing(['router-password']);
    }

    public function test_job_sends_decrypted_credentials_only_to_gateway_and_updates_last_contact_after_success(): void
    {
        [, $router] = $this->routerFixture(['routers.view']);
        $operation = RouterOperation::create([
            'router_id' => $router->id,
            'operation' => 'get_system_info',
            'driver' => $router->driver,
            'transport' => $router->preferred_transport,
            'parameters' => ['credential_profile_id' => '01J00000000000000000000001'],
            'status' => RouterOperation::STATUS_QUEUED,
            'correlation_id' => 'router-job-1',
        ]);
        config(['services.network_automation.url' => 'http://automation.test', 'services.network_automation.token' => 'service-token']);
        Http::fake([
            'http://automation.test/operations' => Http::response([
                'operation' => 'get_system_info',
                'status' => 'succeeded',
                'driver' => 'mikrotik_router',
                'transport' => 'api',
                'correlation_id' => 'router-job-1',
                'message' => 'get_system_info completed.',
                'checked_at' => now()->toIso8601String(),
                'details' => ['data' => ['hostname' => 'edge-1']],
            ]),
        ]);

        (new ExecuteRouterOperation($operation->id))->handle(
            app(\App\Services\NetworkAutomationClient::class),
            app(\App\Services\RouterOperationService::class),
        );

        $operation->refresh();
        $this->assertSame(RouterOperation::STATUS_SUCCEEDED, $operation->status);
        $this->assertNotNull($operation->router->last_contact_at);
        Http::assertSent(fn ($request): bool => $request['credentials']['token'] === 'router-api-token');
        $this->assertStringNotContainsString('router-api-token', $operation->toJson());
    }

    /** @param list<string> $permissions */
    private function routerFixture(array $permissions): array
    {
        $actor = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Router operation org '.$actor->id,
            'slug' => 'router-operation-'.$actor->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'default_currency' => 'PHP',
        ]);
        $organization->users()->attach($actor, ['is_default' => true, 'status' => 'active']);
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Router operation role '.$actor->id, 'guard_name' => 'api']);
        $permissionModels = collect($permissions)->map(fn (string $name): Permission => Permission::firstOrCreate(['name' => $name], ['guard_name' => 'api']));
        $role->permissions()->sync($permissionModels->pluck('id'));
        $role->users()->attach($actor, ['organization_id' => $organization->id]);

        $router = Router::create([
            'name' => 'Operation router '.$actor->id,
            'vendor' => 'mikrotik',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'api',
            'status' => 'active',
            'capabilities' => ['test_connection', 'system_info', 'get_system_info', 'get_interfaces', 'preview_configuration', 'apply_configuration'],
            'management_ip' => '192.0.2.10',
        ]);
        app(RouterCredentialService::class)->storeOrReplace($router, [
            'name' => 'primary',
            'api_token' => 'router-api-token',
        ]);

        return [$actor, $router->fresh()];
    }
}
