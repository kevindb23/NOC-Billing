<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_list_is_isolated_and_includes_assignment_and_permission_counts(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.view');
        $visible = Role::create(['organization_id' => $organization->id, 'name' => 'Visible']);
        $otherOrganization = Organization::factory()->create();
        Role::create(['organization_id' => $otherOrganization->id, 'name' => 'Hidden']);
        $permission = Permission::create(['name' => 'billing.view']);
        $visible->permissions()->attach($permission);
        $assigned = User::factory()->create();
        $organization->users()->attach($assigned, ['status' => 'active']);
        $visible->users()->attach($assigned, ['organization_id' => $organization->id]);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->getJson('/api/v1/roles');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Visible', 'assignment_count' => 1, 'permission_count' => 1]);
        $this->assertNotContains('Hidden', collect($response->json('data'))->pluck('name')->all());
    }

    public function test_role_can_be_created_with_permissions_transactionally_and_audited(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.create');
        $permission = Permission::create(['name' => 'billing.view']);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->postJson('/api/v1/roles', [
            'name' => 'Billing clerk',
            'permission_ids' => [$permission->id],
        ]);

        $role = Role::where('organization_id', $organization->id)->where('name', 'Billing clerk')->firstOrFail();
        $response->assertCreated()->assertJsonPath('data.id', $role->id)->assertJsonPath('data.permission_ids.0', $permission->id);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $permission->id]);
        $this->assertAuditActions(['role.created'], $role);
        $this->assertAuditSecretsAbsent($role);
    }

    public function test_role_details_return_grouped_permission_ids(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.view');
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Support']);
        $first = Permission::create(['name' => 'billing.view']);
        $second = Permission::create(['name' => 'users.view']);
        $role->permissions()->attach([$first->id, $second->id]);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->getJson('/api/v1/roles/'.$role->id)
            ->assertOk()
            ->assertJsonPath('data.permission_ids', [$first->id, $second->id])
            ->assertJsonPath('data.permissions.Billing', [$first->id])
            ->assertJsonPath('data.permissions.System', [$second->id]);
    }

    public function test_role_update_synchronizes_permissions_and_audits_profile_and_permission_changes(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.update');
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Before']);
        $oldPermission = Permission::create(['name' => 'billing.view']);
        $newPermission = Permission::create(['name' => 'billing.update']);
        $role->permissions()->attach($oldPermission);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->putJson('/api/v1/roles/'.$role->id, [
            'name' => 'After',
            'permission_ids' => [$newPermission->id],
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'After')->assertJsonPath('data.permission_ids', [$newPermission->id]);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id, 'permission_id' => $oldPermission->id]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $newPermission->id]);
        $this->assertAuditActions(['role.updated', 'role.permissions_updated'], $role);
    }

    public function test_duplicate_role_names_are_rejected_within_an_organization(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.create');
        Role::create(['organization_id' => $organization->id, 'name' => 'Duplicate']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->postJson('/api/v1/roles', ['name' => 'Duplicate', 'permission_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_assigned_role_cannot_be_deleted(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.delete');
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Assigned']);
        $assigned = User::factory()->create();
        $organization->users()->attach($assigned, ['status' => 'active']);
        $role->users()->attach($assigned, ['organization_id' => $organization->id]);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->deleteJson('/api/v1/roles/'.$role->id)
            ->assertStatus(409);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_global_roles_cannot_be_mutated_through_organization_requests(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.update');
        $global = Role::create(['organization_id' => null, 'name' => 'Global']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->putJson('/api/v1/roles/'.$global->id, ['name' => 'Changed', 'permission_ids' => []])
            ->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $global->id, 'name' => 'Global']);
    }

    public function test_role_crud_requires_the_matching_permission(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.view');
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->getJson('/api/v1/roles')->assertForbidden();
    }

    public function test_invalid_permission_ids_are_rejected(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.create');
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->postJson('/api/v1/roles', ['name' => 'Invalid', 'permission_ids' => [999999]])
            ->assertUnprocessable()->assertJsonValidationErrors(['permission_ids.0']);
        $this->assertDatabaseMissing('roles', ['organization_id' => $organization->id, 'name' => 'Invalid']);
    }

    public function test_role_deletion_is_audited(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.delete');
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Removable']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->deleteJson('/api/v1/roles/'.$role->id)->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertAuditActions(['role.deleted'], $role);
    }

    private function actorWithPermission(string $permissionName): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Permission holder']);
        $permission = Permission::create(['name' => $permissionName]);
        $organization->users()->attach($actor, ['is_default' => true, 'status' => 'active']);
        $role->permissions()->attach($permission);
        $role->users()->attach($actor, ['organization_id' => $organization->id]);

        return [$actor, $organization];
    }

    private function organizationRequest(Organization $organization)
    {
        return $this->withHeader('X-Organization-Id', $organization->public_id);
    }

    private function assertAuditActions(array $actions, Role $role): void
    {
        $this->assertSame($actions, AuditLog::where('auditable_id', $role->id)->orderBy('id')->pluck('action')->all());
    }

    private function assertAuditSecretsAbsent(Role $role): void
    {
        $logs = AuditLog::where('auditable_id', $role->id)->get();
        foreach ($logs as $log) {
            $this->assertArrayNotHasKey('password', $log->old_values ?? []);
            $this->assertArrayNotHasKey('token', $log->new_values ?? []);
        }
    }
}
