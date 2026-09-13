<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouterPermissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.guards.sanctum' => ['driver' => 'session', 'provider' => 'users']]);
    }

    public function test_router_operation_permissions_are_seeded_idempotently(): void
    {
        (new PermissionSeeder)->run();
        (new PermissionSeeder)->run();

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

        foreach ($names as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'api']);
            $this->assertSame(1, Permission::query()->where('name', $name)->count());
        }
    }

    public function test_router_permissions_are_exposed_as_a_roles_group(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Router permission organization',
            'slug' => 'router-permission-organization',
            'status' => 'active',
            'timezone' => 'UTC',
            'default_currency' => 'PHP',
        ]);
        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Permission manager']);
        $role->permissions()->attach(Permission::create(['name' => 'roles.view']));
        $role->users()->attach($user, ['organization_id' => $organization->id]);
        (new PermissionSeeder)->run();
        Sanctum::actingAs($user);

        $catalog = collect($this->getJson('/api/v1/permissions')->assertOk()->json('data'));
        $routers = $catalog->firstWhere('group', 'Routers');

        $this->assertNotNull($routers);
        $this->assertEqualsCanonicalizing(
            [
                'routers.view',
                'routers.create',
                'routers.update',
                'routers.delete',
                'routers.test',
                'routers.export',
                'routers.credentials.view',
                'routers.credentials.manage',
                'routers.operations.view',
                'routers.monitor',
                'routers.configuration.preview',
                'routers.configuration.apply',
                'routers.configuration.commit',
                'routers.configuration.rollback',
            ],
            collect($routers['permissions'])->pluck('name')->all(),
        );
    }
}
