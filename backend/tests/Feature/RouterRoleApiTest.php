<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouterRoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_permissions_are_grouped_as_routers_in_role_details(): void
    {
        $actor = User::factory()->create();
        $accessRole = Role::create(['name' => 'Role viewer']);
        $accessRole->permissions()->attach(Permission::create(['name' => 'roles.view']));
        $accessRole->users()->attach($actor);

        $routerView = Permission::create(['name' => 'routers.view']);
        $routerTest = Permission::create(['name' => 'routers.test']);
        $role = Role::create(['name' => 'Router operator']);
        $role->permissions()->attach([$routerView->id, $routerTest->id]);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/roles/'.$role->id)
            ->assertOk()
            ->assertJsonPath('data.permissions.Routers', [$routerView->id, $routerTest->id])
            ->assertJsonMissingPath('data.permissions.System');
    }
}
