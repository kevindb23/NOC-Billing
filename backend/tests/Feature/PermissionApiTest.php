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
            'data' => [['group', 'permissions' => [['name', 'action']]]],
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

    public function test_catalog_groups_and_actions_follow_the_seeded_order(): void
    {
        [$user, $organization] = $this->userWithPermission('roles.view');
        (new PermissionSeeder())->run();
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Organization-Id', $organization->public_id)
            ->getJson('/api/v1/permissions');

        $response->assertOk()->assertExactJson([
            'data' => [
                ['group' => 'audit-logs', 'permissions' => [
                    ['name' => 'audit-logs.export', 'action' => 'export'],
                    ['name' => 'audit-logs.view', 'action' => 'view'],
                ]],
                ['group' => 'billing', 'permissions' => [
                    ['name' => 'billing.create', 'action' => 'create'],
                    ['name' => 'billing.delete', 'action' => 'delete'],
                    ['name' => 'billing.export', 'action' => 'export'],
                    ['name' => 'billing.update', 'action' => 'update'],
                    ['name' => 'billing.view', 'action' => 'view'],
                ]],
                ['group' => 'dashboard', 'permissions' => [
                    ['name' => 'dashboard.create', 'action' => 'create'],
                    ['name' => 'dashboard.delete', 'action' => 'delete'],
                    ['name' => 'dashboard.export', 'action' => 'export'],
                    ['name' => 'dashboard.update', 'action' => 'update'],
                    ['name' => 'dashboard.view', 'action' => 'view'],
                ]],
                ['group' => 'network', 'permissions' => [
                    ['name' => 'network.create', 'action' => 'create'],
                    ['name' => 'network.delete', 'action' => 'delete'],
                    ['name' => 'network.export', 'action' => 'export'],
                    ['name' => 'network.update', 'action' => 'update'],
                    ['name' => 'network.view', 'action' => 'view'],
                ]],
                ['group' => 'roles', 'permissions' => [
                    ['name' => 'roles.create', 'action' => 'create'],
                    ['name' => 'roles.delete', 'action' => 'delete'],
                    ['name' => 'roles.export', 'action' => 'export'],
                    ['name' => 'roles.update', 'action' => 'update'],
                    ['name' => 'roles.view', 'action' => 'view'],
                ]],
                ['group' => 'system', 'permissions' => [
                    ['name' => 'system.create', 'action' => 'create'],
                    ['name' => 'system.delete', 'action' => 'delete'],
                    ['name' => 'system.export', 'action' => 'export'],
                    ['name' => 'system.update', 'action' => 'update'],
                    ['name' => 'system.view', 'action' => 'view'],
                ]],
                ['group' => 'users', 'permissions' => [
                    ['name' => 'users.create', 'action' => 'create'],
                    ['name' => 'users.delete', 'action' => 'delete'],
                    ['name' => 'users.export', 'action' => 'export'],
                    ['name' => 'users.update', 'action' => 'update'],
                    ['name' => 'users.view', 'action' => 'view'],
                ]],
            ],
        ]);
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
