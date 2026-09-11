<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_list_is_limited_to_active_memberships_in_the_requested_organization(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.view');
        $visible = User::factory()->create(['name' => 'Visible User']);
        $inactiveMember = User::factory()->create(['name' => 'Inactive Member']);
        $otherOrganizationMember = User::factory()->create(['name' => 'Other Organization Member']);
        $organization->users()->attach($visible, ['status' => 'active']);
        $organization->users()->attach($inactiveMember, ['status' => 'suspended']);
        $otherOrganization = Organization::factory()->create();
        $otherOrganization->users()->attach($otherOrganizationMember, ['status' => 'active']);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonPath('data.data.0.membership_status', 'active')
            ->assertJsonStructure(['data' => ['data' => [['public_id', 'name', 'email', 'status', 'membership_status', 'roles', 'created_at', 'updated_at']], 'current_page', 'per_page']]);
        $this->assertSame(['Visible User'], collect($response->json('data.data'))->where('name', 'Visible User')->pluck('name')->all());
    }

    public function test_user_show_returns_role_details_for_the_active_organization_membership(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.view');
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Billing clerk']);
        $user = User::factory()->create();
        $organization->users()->attach($user, ['status' => 'active']);
        $user->rolesForOrganization($organization)->attach($role, ['organization_id' => $organization->id]);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->getJson('/api/v1/users/'.$user->public_id);

        $response->assertOk()->assertJsonPath('data.roles.0.name', 'Billing clerk');
    }

    public function test_user_can_be_created_with_an_organization_membership_and_roles(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.create');
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Support']);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'secret-password',
            'status' => 'active',
            'role_ids' => [$role->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'New User')->assertJsonPath('data.roles.0.name', 'Support');
        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->assertDatabaseHas('role_assignments', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id]);
        $this->assertAuditActions(['user.created', 'user.roles_updated'], $user);
        $this->assertAuditSecretsAbsent($user);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.create');
        User::factory()->create(['email' => 'duplicate@example.com']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->postJson('/api/v1/users', [
            'name' => 'Duplicate', 'email' => 'duplicate@example.com', 'password' => 'secret-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_user_update_replaces_roles_and_audits_profile_and_assignment_changes(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.update');
        $oldRole = Role::create(['organization_id' => $organization->id, 'name' => 'Old role']);
        $newRole = Role::create(['organization_id' => $organization->id, 'name' => 'New role']);
        $user = User::factory()->create(['name' => 'Before']);
        $organization->users()->attach($user, ['status' => 'active']);
        $user->rolesForOrganization($organization)->attach($oldRole, ['organization_id' => $organization->id]);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, [
            'name' => 'After', 'status' => 'active', 'password' => 'new-password', 'role_ids' => [$newRole->id],
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'After')->assertJsonPath('data.roles.0.name', 'New role');
        $this->assertDatabaseMissing('role_assignments', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $oldRole->id]);
        $this->assertDatabaseHas('role_assignments', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $newRole->id]);
        $this->assertAuditActions(['user.updated', 'user.roles_updated'], $user);
        $this->assertAuditSecretsAbsent($user);
    }

    public function test_password_only_update_creates_a_sanitized_user_update_audit(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.update');
        $user = User::factory()->create();
        $organization->users()->attach($user, ['status' => 'active']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, ['password' => 'new-password'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'auditable_id' => $user->id]);
        $this->assertAuditSecretsAbsent($user);
    }

    public function test_user_can_be_deactivated_without_deleting_the_user(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.delete');
        $user = User::factory()->create();
        $organization->users()->attach($user, ['status' => 'active']);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->deleteJson('/api/v1/users/'.$user->public_id);

        $response->assertOk()->assertJsonPath('data.membership_status', 'inactive');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'inactive']);
        $this->assertAuditActions(['user.deactivated'], $user);
        $this->assertAuditSecretsAbsent($user);
    }

    public function test_deactivated_membership_can_be_reactivated_with_roles_and_audited_changes(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.delete');
        $permissionHolder = $organization->roles()->where('name', 'Permission holder')->firstOrFail();
        $permissionHolder->permissions()->attach(Permission::create(['name' => 'users.view']));
        $permissionHolder->permissions()->attach(Permission::create(['name' => 'users.update']));
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Restored role']);
        $user = User::factory()->create();
        $organization->users()->attach($user, ['status' => 'active']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->deleteJson('/api/v1/users/'.$user->public_id)->assertOk();
        $this->organizationRequest($organization)->getJson('/api/v1/users/'.$user->public_id)->assertNotFound();
        $this->assertNotContains($user->public_id, collect($this->organizationRequest($organization)->getJson('/api/v1/users')->json('data.data'))->pluck('public_id')->all());

        $response = $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, [
            'status' => 'active', 'role_ids' => [$role->id],
        ]);

        $response->assertOk()->assertJsonPath('data.membership_status', 'active')->assertJsonPath('data.roles.0.name', 'Restored role');
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->assertDatabaseHas('role_assignments', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id]);
        $this->assertSame(['user.deactivated', 'user.updated', 'user.roles_updated'], AuditLog::where('auditable_id', $user->id)->orderBy('id')->pluck('action')->all());
        $updateAudit = AuditLog::where('auditable_id', $user->id)->where('action', 'user.updated')->firstOrFail();
        $this->assertSame('inactive', $updateAudit->old_values['membership_status']);
        $this->assertSame('active', $updateAudit->new_values['membership_status']);
        $rolesAudit = AuditLog::where('auditable_id', $user->id)->where('action', 'user.roles_updated')->firstOrFail();
        $this->assertSame([], $rolesAudit->old_values['roles']);
        $this->assertSame([['id' => $role->id, 'name' => 'Restored role']], $rolesAudit->new_values['roles']);
    }

    public function test_last_active_administrator_cannot_be_deactivated(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.delete');
        $administrator = Role::create(['organization_id' => $organization->id, 'name' => 'Administrator']);
        $user = User::factory()->create();
        $organization->users()->attach($user, ['status' => 'active']);
        $user->rolesForOrganization($organization)->attach($administrator, ['organization_id' => $organization->id]);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->deleteJson('/api/v1/users/'.$user->public_id)
            ->assertStatus(422)->assertJsonPath('message', 'The organization must retain at least one active administrator.');
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
    }

    public function test_role_ids_from_another_organization_are_rejected(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.create');
        $otherOrganization = Organization::factory()->create();
        $role = Role::create(['organization_id' => $otherOrganization->id, 'name' => 'Foreign role']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->postJson('/api/v1/users', [
            'name' => 'Invalid assignment', 'email' => 'invalid-assignment@example.com', 'password' => 'secret-password', 'role_ids' => [$role->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['role_ids.0']);
    }

    public function test_user_crud_requires_the_matching_permission(): void
    {
        [$actor, $organization] = $this->actorWithPermission('roles.view');
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->getJson('/api/v1/users')->assertForbidden();
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

    private function assertAuditActions(array $actions, User $user): void
    {
        $this->assertSame($actions, AuditLog::where('auditable_id', $user->id)->orderBy('id')->pluck('action')->all());
    }

    private function assertAuditSecretsAbsent(User $user): void
    {
        $logs = AuditLog::where('auditable_id', $user->id)->get();
        foreach ($logs as $log) {
            $this->assertArrayNotHasKey('password', $log->old_values ?? []);
            $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        }
    }
}
