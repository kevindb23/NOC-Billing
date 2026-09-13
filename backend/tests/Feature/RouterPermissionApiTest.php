<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouterPermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_permissions_are_exposed_as_a_roles_group(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Permission manager']);
        $role->permissions()->attach(Permission::create(['name' => 'roles.view']));
        $user->roles()->attach($role);
        (new PermissionSeeder)->run();
        Sanctum::actingAs($user);

        $catalog = collect($this->getJson('/api/v1/permissions')->assertOk()->json('data'));
        $routers = $catalog->firstWhere('group', 'Routers');

        $this->assertNotNull($routers);
        $this->assertSame(
            ['view', 'create', 'update', 'delete', 'test', 'export'],
            collect($routers['permissions'])->pluck('action')->all(),
        );
        $this->assertSame(
            [
                'routers.view',
                'routers.create',
                'routers.update',
                'routers.delete',
                'routers.test',
                'routers.export',
            ],
            collect($routers['permissions'])->pluck('name')->all(),
        );
    }
}
