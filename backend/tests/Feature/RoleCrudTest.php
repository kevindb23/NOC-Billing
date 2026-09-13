<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_list_is_installation_wide_and_includes_counts(): void
    {
        [$actor] = $this->actorWithPermission('roles.view');
        $visible = Role::create(['name' => 'Visible']);
        Role::create(['name' => 'Other role']);
        $visible->permissions()->attach(Permission::create(['name' => 'billing.view']));
        $assigned = User::factory()->create();
        $visible->users()->attach($assigned);
        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/roles');

        $response->assertOk()->assertJsonFragment(['name' => 'Visible', 'assignment_count' => 1, 'permission_count' => 1]);
        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_role_list_is_paginated_without_scope_fields(): void
    {
        [$actor] = $this->actorWithPermission('roles.view');
        Role::create(['name' => 'Installation role']);
        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/roles?per_page=2');

        $response->assertOk()->assertJsonPath('data.current_page', 1)->assertJsonPath('data.per_page', 2);
        foreach ($response->json('data.data') as $role) {
            $this->assertArrayNotHasKey('scope', $role);
            $this->assertArrayNotHasKey('organization_id', $role);
        }
    }

    public function test_role_can_be_created_with_permissions_transactionally_and_audited(): void
    {
        [$actor] = $this->actorWithPermission('roles.create');
        $permission = Permission::create(['name' => 'billing.view']);
        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/roles', ['name' => 'Billing clerk', 'permission_ids' => [$permission->id]]);

        $role = Role::where('name', 'Billing clerk')->firstOrFail();
        $response->assertCreated()->assertJsonPath('data.id', $role->id)->assertJsonPath('data.permission_ids.0', $permission->id);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $permission->id]);
        $this->assertAuditActions(['role.created'], $role);
    }

    public function test_role_details_return_grouped_permission_ids(): void
    {
        [$actor] = $this->actorWithPermission('roles.view');
        $role = Role::create(['name' => 'Support']);
        $first = Permission::create(['name' => 'billing.view']);
        $second = Permission::create(['name' => 'users.view']);
        $role->permissions()->attach([$first->id, $second->id]);
        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/roles/'.$role->id)->assertOk()
            ->assertJsonPath('data.permission_ids', [$first->id, $second->id])
            ->assertJsonPath('data.permissions.Billing', [$first->id])
            ->assertJsonPath('data.permissions.System', [$second->id]);
    }

    public function test_role_update_synchronizes_permissions_and_audits_changes(): void
    {
        [$actor] = $this->actorWithPermission('roles.update');
        $role = Role::create(['name' => 'Before']);
        $old = Permission::create(['name' => 'billing.view']);
        $new = Permission::create(['name' => 'billing.update']);
        $role->permissions()->attach($old);
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/roles/'.$role->id, ['name' => 'After', 'permission_ids' => [$new->id]])->assertOk();

        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id, 'permission_id' => $old->id]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'permission_id' => $new->id]);
        $this->assertAuditActions(['role.updated', 'role.permissions_updated'], $role);
    }

    public function test_duplicate_role_names_are_rejected_globally(): void
    {
        [$actor] = $this->actorWithPermission('roles.create');
        Role::create(['name' => 'Duplicate']);
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/roles', ['name' => 'Duplicate', 'permission_ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_assigned_role_cannot_be_deleted(): void
    {
        [$actor] = $this->actorWithPermission('roles.delete');
        $role = Role::create(['name' => 'Assigned']);
        $role->users()->attach(User::factory()->create());
        Sanctum::actingAs($actor);

        $this->deleteJson('/api/v1/roles/'.$role->id)->assertStatus(409);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_role_creation_rolls_back_when_permission_sync_fails(): void
    {
        [$actor] = $this->actorWithPermission('roles.create');
        $permission = Permission::create(['name' => 'billing.view']);
        DB::statement("CREATE TRIGGER fail_role_permission_insert BEFORE INSERT ON role_permissions BEGIN SELECT RAISE(ABORT, 'forced permission sync failure'); END");
        Sanctum::actingAs($actor);

        try {
            $this->postJson('/api/v1/roles', ['name' => 'Rolled back', 'permission_ids' => [$permission->id]])->assertServerError();
        } finally {
            DB::statement('DROP TRIGGER fail_role_permission_insert');
        }

        $this->assertDatabaseMissing('roles', ['name' => 'Rolled back']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'role.created']);
    }

    public function test_role_crud_requires_the_matching_permission(): void
    {
        [$actor] = $this->actorWithPermission('users.view');
        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/roles')->assertForbidden();
    }

    public function test_invalid_permission_ids_are_rejected(): void
    {
        [$actor] = $this->actorWithPermission('roles.create');
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/roles', ['name' => 'Invalid', 'permission_ids' => [999999]])
            ->assertUnprocessable()->assertJsonValidationErrors(['permission_ids.0']);
        $this->assertDatabaseMissing('roles', ['name' => 'Invalid']);
    }

    public function test_installation_role_can_be_deleted_and_is_audited(): void
    {
        [$actor] = $this->actorWithPermission('roles.delete');
        $role = Role::create(['name' => 'Removable']);
        Sanctum::actingAs($actor);

        $this->deleteJson('/api/v1/roles/'.$role->id)->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertAuditActions(['role.deleted'], $role);
    }

    private function actorWithPermission(string $permissionName): array
    {
        $actor = User::factory()->create();
        $role = Role::create(['name' => 'Permission holder']);
        $role->permissions()->attach(Permission::create(['name' => $permissionName]));
        $role->users()->attach($actor);

        return [$actor];
    }

    private function assertAuditActions(array $actions, Role $role): void
    {
        $this->assertSame($actions, AuditLog::where('auditable_id', $role->id)->orderBy('id')->pluck('action')->all());
    }
}
