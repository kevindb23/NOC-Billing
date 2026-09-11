<?php

namespace Tests\Feature;

use App\Models\Organization;
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

    public function test_user_with_the_catalog_permission_can_read_the_catalog(): void
    {
        [$user, $organization] = $this->userWithPermission('roles.view');
        (new PermissionSeeder())->run();
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Organization-Id', $organization->public_id)
            ->getJson('/api/v1/permissions');

        $response->assertOk()->assertJsonStructure([
            'data' => [['group', 'permissions' => [['id', 'name', 'action']]]],
        ]);
    }

    public function test_user_without_the_catalog_permission_receives_json_forbidden(): void
    {
        [$user, $organization] = $this->userWithPermission('users.view');
        (new PermissionSeeder())->run();
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Organization-Id', $organization->public_id)
            ->getJson('/api/v1/permissions');

        $response->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_catalog_permission_does_not_cross_organization_boundaries(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['organization_id' => $otherOrganization->id, 'name' => 'Other organization manager']);
        $permission = Permission::create(['name' => 'roles.view']);

        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        $otherOrganization->users()->attach($user, ['is_default' => false, 'status' => 'active']);
        $role->permissions()->attach($permission);
        $role->users()->attach($user, ['organization_id' => $otherOrganization->id]);
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Organization-Id', $organization->public_id)
            ->getJson('/api/v1/permissions');

        $response->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_catalog_uses_authority_groups_and_action_order(): void
    {
        [$user, $organization] = $this->userWithPermission('roles.view');
        (new PermissionSeeder())->run();
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Organization-Id', $organization->public_id)
            ->getJson('/api/v1/permissions');

        $response->assertOk();
        $catalog = collect($response->json('data'));
        $this->assertSame(['Dashboard', 'Billing', 'Network', 'System'], $catalog->pluck('group')->all());
        $systemActions = collect($catalog->last()['permissions'])->pluck('action')->all();
        $this->assertSame(['view', 'view', 'view', 'view', 'create', 'create', 'create', 'update', 'update', 'update', 'delete', 'delete', 'delete', 'export', 'export', 'export', 'export'], $systemActions);
        foreach ($catalog->pluck('permissions')->flatten(1) as $permission) {
            $this->assertArrayHasKey('id', $permission);
            $this->assertSame(Permission::where('name', $permission['name'])->value('id'), $permission['id']);
        }
    }

    private function userWithPermission(string $permissionName): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Permission manager']);
        $permission = Permission::create(['name' => $permissionName]);

        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        $role->permissions()->attach($permission);
        $role->users()->attach($user, ['organization_id' => $organization->id]);

        return [$user, $organization];
    }
}
