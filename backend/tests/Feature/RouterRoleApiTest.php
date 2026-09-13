<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouterRoleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.guards.sanctum' => ['driver' => 'session', 'provider' => 'users']]);
    }

    public function test_administrator_role_receives_router_operation_permissions_once(): void
    {
        $role = Role::create(['name' => 'Administrator', 'guard_name' => 'api']);
        $migration = require database_path('migrations/2026_09_13_000026_add_router_operation_permissions.php');

        $migration->up();
        $migration->up();

        $names = [
            'routers.credentials.view',
            'routers.credentials.manage',
            'routers.operations.view',
            'routers.monitor',
            'routers.configuration.preview',
            'routers.configuration.apply',
            'routers.configuration.commit',
            'routers.configuration.rollback',
        ];

        $this->assertSame(8, $role->fresh()->permissions()->whereIn('name', $names)->count());
        $this->assertSame(1, Permission::query()->where('name', 'routers.monitor')->count());
    }

    public function test_router_permissions_are_grouped_as_routers_in_role_details(): void
    {
        $actor = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Router role organization',
            'slug' => 'router-role-organization',
            'status' => 'active',
            'timezone' => 'UTC',
            'default_currency' => 'PHP',
        ]);
        $organization->users()->attach($actor, ['is_default' => true, 'status' => 'active']);
        $accessRole = Role::create(['organization_id' => $organization->id, 'name' => 'Role viewer']);
        $accessRole->permissions()->attach(Permission::create(['name' => 'roles.view']));
        $accessRole->users()->attach($actor, ['organization_id' => $organization->id]);

        $routerView = Permission::create(['name' => 'routers.view']);
        $routerTest = Permission::create(['name' => 'routers.test']);
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Router operator']);
        $role->permissions()->attach([$routerView->id, $routerTest->id]);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/roles/'.$role->id)
            ->assertOk()
            ->assertJsonPath('data.permissions.Routers', [$routerView->id, $routerTest->id])
            ->assertJsonMissingPath('data.permissions.System');
    }
}
