<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_the_catalog_permission_can_read_the_catalog_without_an_installation_header(): void
    {
        $user = $this->userWithPermission('roles.view');
        (new PermissionSeeder())->run();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonStructure(['data' => [['group', 'permissions' => [['id', 'name', 'action']]]]]);
    }

    public function test_user_without_the_catalog_permission_receives_json_forbidden(): void
    {
        $user = $this->userWithPermission('users.view');
        (new PermissionSeeder())->run();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/permissions')
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_catalog_permission_is_installation_wide(): void
    {
        $user = $this->userWithPermission('roles.view');
        (new PermissionSeeder())->run();
        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', 'legacy-header-is-ignored')
            ->getJson('/api/v1/permissions')
            ->assertOk();
    }

    public function test_catalog_uses_authority_groups_and_action_order(): void
    {
        $user = $this->userWithPermission('roles.view');
        (new PermissionSeeder())->run();
        Sanctum::actingAs($user);

        $catalog = collect($this->getJson('/api/v1/permissions')->assertOk()->json('data'));
        $this->assertContains('Paymongo', $catalog->pluck('group')->all());
        $this->assertContains('API Tokens', $catalog->pluck('group')->all());
        $paymongo = $catalog->firstWhere('group', 'Paymongo');
        $this->assertSame(['view', 'update', 'test'], collect($paymongo['permissions'])->pluck('action')->all());
        $this->assertSame($catalog->pluck('group')->sort()->values()->all(), $catalog->pluck('group')->values()->all());
        foreach ($catalog->pluck('permissions')->flatten(1) as $permission) {
            $this->assertSame(Permission::where('name', $permission['name'])->value('id'), $permission['id']);
        }
    }

    private function userWithPermission(string $permissionName): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Permission manager']);
        $role->permissions()->attach(Permission::create(['name' => $permissionName]));
        $user->roles()->attach($role);

        return $user;
    }
}
