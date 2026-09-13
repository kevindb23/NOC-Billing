<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Router;
use App\Models\User;
use App\Services\RouterDriverRegistry;
use App\Services\RouterManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_api_supports_paginated_crud_and_normalized_driver_actions(): void
    {
        $actor = $this->actorWithRouterPermissions();
        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/routers?per_page=5')
            ->assertOk()
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.data', []);

        $created = $this->postJson('/api/v1/routers', [
            'name' => 'Core MikroTik',
            'hostname' => 'core-router.example.test',
            'management_ip' => '192.0.2.10',
            'vendor' => 'mikrotik',
            'model' => 'CCR2004',
            'software_version' => '7.15',
            'serial_number' => 'MT-123',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'mock',
            'status' => 'active',
            'notes' => 'Core router',
            'created_by' => User::factory()->create()->id,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Core MikroTik')
            ->assertJsonPath('data.created_by', $actor->id)
            ->assertJsonPath('data.driver', 'mikrotik_router');

        $publicId = $created->json('data.public_id');
        $this->assertNotEmpty($publicId);

        $this->getJson('/api/v1/routers/'.$publicId)
            ->assertOk()
            ->assertJsonPath('data.public_id', $publicId)
            ->assertJsonPath('data.management_ip', '192.0.2.10');

        $this->putJson('/api/v1/routers/'.$publicId, [
            'name' => 'Core MikroTik Updated',
            'status' => 'maintenance',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Core MikroTik Updated')
            ->assertJsonPath('data.status', 'maintenance');

        $this->postJson('/api/v1/routers/'.$publicId.'/connection-test')
            ->assertOk()
            ->assertJsonPath('data.status', 'not_configured')
            ->assertJsonPath('data.driver', 'mikrotik_router');

        $this->getJson('/api/v1/routers/'.$publicId.'/system-info')
            ->assertOk()
            ->assertJsonPath('data.status', 'not_configured')
            ->assertJsonPath('data.vendor', 'mikrotik');

        $this->deleteJson('/api/v1/routers/'.$publicId)->assertNoContent();
        $this->assertSoftDeleted('routers', ['public_id' => $publicId]);
        $this->getJson('/api/v1/routers/'.$publicId)->assertNotFound();
    }

    public function test_router_routes_require_router_permissions(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);
        $router = Router::create($this->routerAttributes('Protected router'));

        $routes = [
            ['GET', '/api/v1/routers'],
            ['POST', '/api/v1/routers'],
            ['GET', '/api/v1/routers/'.$router->public_id],
            ['PUT', '/api/v1/routers/'.$router->public_id],
            ['DELETE', '/api/v1/routers/'.$router->public_id],
            ['POST', '/api/v1/routers/'.$router->public_id.'/connection-test'],
            ['GET', '/api/v1/routers/'.$router->public_id.'/system-info'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertForbidden();
        }
    }

    public function test_router_lifecycle_and_driver_actions_write_audits_without_secrets(): void
    {
        $actor = $this->actorWithRouterPermissions();
        Sanctum::actingAs($actor);

        $created = $this->withHeader('X-Request-Id', 'router-create-correlation')->postJson('/api/v1/routers', [
            ...$this->routerAttributes('Audited router'),
            'metadata' => ['site' => 'main', 'credentials' => ['password' => 'do-not-log']],
        ])->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->withHeader('X-Request-Id', 'router-update-correlation')
            ->putJson('/api/v1/routers/'.$publicId, ['name' => 'Audited router updated'])
            ->assertOk();
        $this->withHeader('X-Request-Id', 'router-test-correlation')
            ->postJson('/api/v1/routers/'.$publicId.'/connection-test')
            ->assertOk();
        $this->withHeader('X-Request-Id', 'router-info-correlation')
            ->getJson('/api/v1/routers/'.$publicId.'/system-info')
            ->assertOk();
        $this->withHeader('X-Request-Id', 'router-delete-correlation')
            ->deleteJson('/api/v1/routers/'.$publicId)
            ->assertNoContent();

        $logs = AuditLog::query()
            ->where('auditable_type', Router::class)
            ->where('auditable_id', Router::withTrashed()->where('public_id', $publicId)->value('id'))
            ->orderBy('id')
            ->get();

        $this->assertSame([
            'router.created',
            'router.updated',
            'router.connection_tested',
            'router.system_info_requested',
            'router.deleted',
        ], $logs->pluck('action')->all());
        $this->assertSame($actor->id, $logs->first()->actor_user_id);
        $this->assertSame('router-create-correlation', $logs->first()->correlation_id);
        $this->assertSame('created', $logs->first()->new_values['result']);
        $this->assertSame('Audited router', $logs->get(1)->old_values['name']);
        $this->assertSame('Audited router updated', $logs->get(1)->new_values['name']);
        $this->assertSame('not_configured', $logs->get(2)->new_values['result']['status']);
        $this->assertSame('not_configured', $logs->get(3)->new_values['result']['status']);
        $this->assertSame('deleted', $logs->last()->new_values['result']);

        $encodedLogs = json_encode($logs->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('do-not-log', $encodedLogs);
        $this->assertStringNotContainsString('password', $encodedLogs);
    }

    public function test_router_index_paginates_populated_results_and_excludes_soft_deleted_routers(): void
    {
        $actor = $this->actorWithRouterPermissions();
        Sanctum::actingAs($actor);
        Router::create($this->routerAttributes('Router 1'));
        $second = Router::create($this->routerAttributes('Router 2'));
        $deleted = Router::create($this->routerAttributes('Router 3'));
        $deleted->delete();

        $this->getJson('/api/v1/routers?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.per_page', 1)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.name', $second->name)
            ->assertJsonMissing(['name' => $deleted->name]);
    }

    public function test_router_index_rejects_invalid_per_page_bounds(): void
    {
        $actor = $this->actorWithRouterPermissions();
        Sanctum::actingAs($actor);

        foreach ([-1, 0, 101, 'many'] as $perPage) {
            $this->getJson('/api/v1/routers?per_page='.urlencode((string) $perPage))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['per_page']);
        }
    }

    public function test_router_name_remains_reserved_after_soft_delete(): void
    {
        $actor = $this->actorWithRouterPermissions();
        Sanctum::actingAs($actor);
        $router = Router::create($this->routerAttributes('Reserved router'));
        $this->deleteJson('/api/v1/routers/'.$router->public_id)->assertNoContent();

        $this->postJson('/api/v1/routers', [
            ...$this->routerAttributes($router->name),
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_router_api_validates_fields_and_unknown_driver_actions_are_controlled(): void
    {
        $actor = $this->actorWithRouterPermissions();
        Sanctum::actingAs($actor);

        Router::create([
            'name' => 'Existing router',
            'vendor' => 'other',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'mock',
            'status' => 'unknown',
        ]);

        $this->postJson('/api/v1/routers', [
            'name' => 'Existing router',
            'vendor' => 'other',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'mock',
            'status' => 'unknown',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $this->postJson('/api/v1/routers', [
            'name' => 'Invalid router',
            'vendor' => 'unknown-vendor',
            'driver' => 'unknown_router',
            'preferred_transport' => 'telnet',
            'status' => 'online',
            'management_ip' => 'not-an-ip',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor', 'driver', 'preferred_transport', 'status', 'management_ip']);

        $router = Router::create([
            'name' => 'Unknown driver router',
            'vendor' => 'other',
            'driver' => 'unknown_router',
            'preferred_transport' => 'mock',
            'status' => 'unknown',
        ]);

        $this->postJson('/api/v1/routers/'.$router->public_id.'/connection-test')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Unknown router driver [unknown_router].');
    }

    public function test_router_api_returns_422_for_an_unsupported_driver_operation(): void
    {
        $actor = $this->actorWithRouterPermissions();
        Sanctum::actingAs($actor);
        $limited = new \App\Drivers\Router\UnavailableRouterDriver('limited_router', 'test', ['connection_test']);
        $this->app->instance(RouterManager::class, new RouterManager(new RouterDriverRegistry(['limited_router' => $limited])));
        $router = Router::create([
            'name' => 'Limited router',
            'vendor' => 'other',
            'driver' => 'limited_router',
            'preferred_transport' => 'mock',
            'status' => 'unknown',
        ]);

        $this->getJson('/api/v1/routers/'.$router->public_id.'/system-info')
            ->assertUnprocessable()
            ->assertJsonPath('data.status', 'unsupported')
            ->assertJsonPath('message', 'System information is not supported by this driver.');
    }

    private function actorWithRouterPermissions(): User
    {
        $actor = User::factory()->create();
        $role = Role::create(['name' => 'Router manager', 'guard_name' => 'api']);
        $permissions = collect([
            'routers.view',
            'routers.create',
            'routers.update',
            'routers.delete',
            'routers.test',
            'routers.export',
        ])->map(fn (string $name): Permission => Permission::create(['name' => $name, 'guard_name' => 'api']));
        $role->permissions()->sync($permissions->pluck('id'));
        $role->users()->attach($actor);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function routerAttributes(string $name): array
    {
        return [
            'name' => $name,
            'vendor' => 'mikrotik',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'mock',
            'status' => 'unknown',
        ];
    }

    public function test_router_persistence_contract_is_installation_wide_and_relational(): void
    {
        $this->assertTrue(Schema::hasTable('routers'));
        $this->assertNotContains('organization_id', Schema::getColumnListing('routers'));
        $this->assertContains('created_by', Schema::getColumnListing('routers'));

        $createdBy = collect(Schema::getColumns('routers'))->firstWhere('name', 'created_by');
        $this->assertTrue($createdBy['nullable']);
        $this->assertTrue(collect(Schema::getForeignKeys('routers'))->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['created_by']
                && $foreignKey['foreign_table'] === 'users',
        ));

        $indexes = Schema::getIndexes('routers');
        $uniqueIndexes = collect($indexes)
            ->filter(fn (array $index): bool => $index['unique'] ?? false)
            ->map(fn (array $index): array => $index['columns'])
            ->values()
            ->all();

        $this->assertContains(['public_id'], $uniqueIndexes);
        $this->assertContains(['name'], $uniqueIndexes);

        $indexedColumns = collect($indexes)
            ->flatMap(fn (array $index): array => $index['columns'])
            ->unique()
            ->values()
            ->all();
        $this->assertContains('vendor', $indexedColumns);
        $this->assertContains('driver', $indexedColumns);
        $this->assertContains('status', $indexedColumns);
        $this->assertContains('management_ip', $indexedColumns);
        $this->assertContains('last_contact_at', $indexedColumns);

        $user = User::factory()->create();
        $router = Router::create([
            'name' => 'Core MikroTik',
            'hostname' => 'core-router.example.test',
            'management_ip' => '192.0.2.10',
            'vendor' => 'mikrotik',
            'model' => 'CCR2004',
            'software_version' => '7.15',
            'serial_number' => 'MT-123',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'api',
            'status' => 'active',
            'capabilities' => ['system_info' => true],
            'last_contact_at' => '2026-09-13 12:00:00',
            'last_synchronized_at' => '2026-09-13 12:01:00',
            'notes' => 'Core router',
            'metadata' => ['site' => 'main'],
        ]);

        $this->assertNotEmpty($router->public_id);
        $this->assertNull($router->created_by);
        $this->assertIsArray($router->capabilities);
        $this->assertIsArray($router->metadata);
        $this->assertInstanceOf(Carbon::class, $router->last_contact_at);
        $this->assertInstanceOf(Carbon::class, $router->last_synchronized_at);

        $router->created_by = $user->id;
        $router->save();

        $this->assertTrue($router->creator->is($user));

        AuditLog::create([
            'actor_user_id' => $user->id,
            'action' => 'router.created',
            'auditable_type' => Router::class,
            'auditable_id' => $router->id,
        ]);

        $this->assertTrue($router->auditLogs()->exists());

        $router->delete();

        $this->assertInstanceOf(Carbon::class, $router->deleted_at);
    }
}
