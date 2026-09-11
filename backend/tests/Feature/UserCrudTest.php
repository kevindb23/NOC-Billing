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

    public function test_user_list_includes_active_and_inactive_memberships_in_the_requested_organization(): void
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
        $this->assertSame(['Visible User', 'Inactive Member'], collect($response->json('data.data'))->whereIn('name', ['Visible User', 'Inactive Member'])->pluck('name')->all());
    }

    public function test_user_list_can_search_name_or_email(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.view');
        $matching = User::factory()->create(['name' => 'Searchable User', 'email' => 'match@example.com']);
        $other = User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);
        $organization->users()->attach($matching, ['status' => 'inactive']);
        $organization->users()->attach($other, ['status' => 'active']);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->getJson('/api/v1/users?search=match');

        $response->assertOk();
        $this->assertSame(['Searchable User'], collect($response->json('data.data'))->pluck('name')->all());
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

    public function test_inactive_user_creation_keeps_membership_inactive_and_rejects_login(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.create');
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->postJson('/api/v1/users', [
            'name' => 'Inactive User',
            'email' => 'inactive-user@example.com',
            'password' => 'secret-password',
            'status' => 'inactive',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'inactive')->assertJsonPath('data.membership_status', 'inactive');
        $user = User::where('email', 'inactive-user@example.com')->firstOrFail();
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'inactive']);
        $createdAudit = AuditLog::where('auditable_id', $user->id)->where('action', 'user.created')->firstOrFail();
        $this->assertSame('inactive', $createdAudit->new_values['status']);
        $this->assertSame('inactive', $createdAudit->new_values['membership_status']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'])->assertUnprocessable();
    }

    public function test_user_status_put_synchronizes_membership_status_and_audits_each_transition(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.update');
        $user = User::factory()->create(['status' => 'active']);
        $organization->users()->attach($user, ['status' => 'active']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive')->assertJsonPath('data.membership_status', 'inactive');
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'inactive']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();

        $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.membership_status', 'active');
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        $audits = AuditLog::where('auditable_id', $user->id)->orderBy('id')->get();
        $this->assertSame(['user.updated', 'user.activated'], $audits->pluck('action')->all());
        $this->assertSame('active', $audits[0]->old_values['status']);
        $this->assertSame('inactive', $audits[0]->new_values['status']);
        $this->assertSame('active', $audits[0]->old_values['membership_status']);
        $this->assertSame('inactive', $audits[0]->new_values['membership_status']);
        $this->assertSame('inactive', $audits[1]->old_values['status']);
        $this->assertSame('active', $audits[1]->new_values['status']);
        $this->assertSame('inactive', $audits[1]->old_values['membership_status']);
        $this->assertSame('active', $audits[1]->new_values['membership_status']);
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

    public function test_user_can_be_deactivated_without_deleting_the_user_and_audits_status_transition(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.delete');
        $user = User::factory()->create();
        $organization->users()->attach($user, ['status' => 'active']);
        Sanctum::actingAs($actor);

        $response = $this->organizationRequest($organization)->deleteJson('/api/v1/users/'.$user->public_id);

        $response->assertOk()->assertJsonPath('data.membership_status', 'inactive');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'inactive']);
        $this->assertAuditActions(['user.deactivated'], $user);
        $audit = AuditLog::where('auditable_id', $user->id)->firstOrFail();
        $this->assertSame($actor->id, $audit->actor_user_id);
        $this->assertSame($organization->id, $audit->organization_id);
        $this->assertSame('active', $audit->old_values['status']);
        $this->assertSame('inactive', $audit->new_values['status']);
        $this->assertSame('active', $audit->old_values['membership_status']);
        $this->assertSame('inactive', $audit->new_values['membership_status']);
        $this->assertAuditSecretsAbsent($user);
        $this->assertAuditTokensAbsent($user);
    }

    public function test_status_activation_emits_user_activated_instead_of_user_updated(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.update');
        $user = User::factory()->create(['status' => 'inactive']);
        $organization->users()->attach($user, ['status' => 'inactive']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.membership_status', 'active');

        $audits = AuditLog::where('auditable_id', $user->id)->orderBy('id')->get();
        $this->assertSame(['user.activated'], $audits->pluck('action')->all());
        $audit = $audits->first();
        $this->assertSame($actor->id, $audit->actor_user_id);
        $this->assertSame($organization->id, $audit->organization_id);
        $this->assertSame('inactive', $audit->old_values['status']);
        $this->assertSame('active', $audit->new_values['status']);
        $this->assertSame('inactive', $audit->old_values['membership_status']);
        $this->assertSame('active', $audit->new_values['membership_status']);
        $this->assertAuditSecretsAbsent($user);
        $this->assertAuditTokensAbsent($user);
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
        $listedInactive = collect($this->organizationRequest($organization)->getJson('/api/v1/users')->json('data.data'))->firstWhere('public_id', $user->public_id);
        $this->assertSame('inactive', $listedInactive['membership_status']);

        $response = $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, [
            'status' => 'active', 'role_ids' => [$role->id],
        ]);

        $response->assertOk()->assertJsonPath('data.membership_status', 'active')->assertJsonPath('data.roles.0.name', 'Restored role');
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->assertDatabaseHas('role_assignments', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => $role->id]);
        $this->assertSame(['user.deactivated', 'user.activated', 'user.roles_updated'], AuditLog::where('auditable_id', $user->id)->orderBy('id')->pluck('action')->all());
        $updateAudit = AuditLog::where('auditable_id', $user->id)->where('action', 'user.activated')->firstOrFail();
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

    public function test_last_active_administrator_cannot_be_updated_inactive(): void
    {
        [$actor, $organization] = $this->actorWithPermission('users.update');
        $administrator = Role::create(['organization_id' => $organization->id, 'name' => 'Administrator']);
        $user = User::factory()->create(['status' => 'active']);
        $organization->users()->attach($user, ['status' => 'active']);
        $user->rolesForOrganization($organization)->attach($administrator, ['organization_id' => $organization->id]);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, ['status' => 'inactive'])
            ->assertStatus(422)->assertJsonPath('message', 'The organization must retain at least one active administrator.');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
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

    private function assertAuditTokensAbsent(User $user): void
    {
        $logs = AuditLog::where('auditable_id', $user->id)->get();
        foreach ($logs as $log) {
            $this->assertArrayNotHasKey('token', $log->old_values ?? []);
            $this->assertArrayNotHasKey('token', $log->new_values ?? []);
            $this->assertArrayNotHasKey('access_token', $log->old_values ?? []);
            $this->assertArrayNotHasKey('access_token', $log->new_values ?? []);
        }
    }
}
