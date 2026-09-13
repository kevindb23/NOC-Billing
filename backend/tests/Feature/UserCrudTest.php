<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

    public function test_user_list_includes_active_and_inactive_installation_users(): void
    {
        [$actor] = $this->actorWithPermission('users.view');
        User::factory()->create(['name' => 'Visible User', 'status' => 'active']);
        User::factory()->create(['name' => 'Inactive User', 'status' => 'inactive']);
        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk()->assertJsonStructure(['data' => ['data' => [['public_id', 'name', 'email', 'status', 'roles', 'created_at', 'updated_at']], 'current_page', 'per_page']]);
        $this->assertSame(['Visible User', 'Inactive User'], collect($response->json('data.data'))->whereIn('name', ['Visible User', 'Inactive User'])->pluck('name')->all());
    }

    public function test_user_list_can_search_name_or_email(): void
    {
        [$actor] = $this->actorWithPermission('users.view');
        User::factory()->create(['name' => 'Searchable User', 'email' => 'match@example.com']);
        User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);
        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/users?search=match')->assertOk();
        $this->assertSame(['Searchable User'], collect($this->getJson('/api/v1/users?search=match')->json('data.data'))->pluck('name')->all());
    }

    public function test_user_show_returns_global_role_details(): void
    {
        [$actor] = $this->actorWithPermission('users.view');
        $role = Role::create(['name' => 'Billing clerk']);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/users/'.$user->public_id)->assertOk()->assertJsonPath('data.roles.0.name', 'Billing clerk');
    }

    public function test_user_can_be_created_with_global_roles_and_audited(): void
    {
        [$actor] = $this->actorWithPermission('users.create');
        $role = Role::create(['name' => 'Support']);
        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New User', 'email' => 'new-user@example.com', 'password' => 'secret-password', 'status' => 'active', 'role_ids' => [$role->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.roles.0.name', 'Support');
        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertDatabaseHas('role_assignments', ['user_id' => $user->id, 'role_id' => $role->id]);
        $this->assertSame(['user.created'], AuditLog::where('auditable_id', $user->id)->pluck('action')->all());
        $this->assertAuditSecretsAbsent($user);
    }

    public function test_inactive_user_creation_rejects_login(): void
    {
        [$actor] = $this->actorWithPermission('users.create');
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/users', [
            'name' => 'Inactive User', 'email' => 'inactive-user@example.com', 'password' => 'secret-password', 'status' => 'inactive',
        ])->assertCreated()->assertJsonPath('data.status', 'inactive');

        $this->postJson('/api/v1/auth/login', ['email' => 'inactive-user@example.com', 'password' => 'secret-password'])
            ->assertStatus(422);
    }

    public function test_user_status_updates_are_global_and_audited(): void
    {
        [$actor] = $this->actorWithPermission('users.update');
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/users/'.$user->public_id, ['status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->putJson('/api/v1/users/'.$user->public_id, ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertSame(['user.updated', 'user.updated'], AuditLog::where('auditable_id', $user->id)->pluck('action')->all());
        $this->assertSame('active', AuditLog::where('auditable_id', $user->id)->latest('id')->first()->new_values['status']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        [$actor] = $this->actorWithPermission('users.create');
        User::factory()->create(['email' => 'duplicate@example.com']);
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/users', ['name' => 'Duplicate', 'email' => 'duplicate@example.com', 'password' => 'secret-password'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_user_update_replaces_global_roles_and_audits_profile_change(): void
    {
        [$actor] = $this->actorWithPermission('users.update');
        $old = Role::create(['name' => 'Old role']);
        $new = Role::create(['name' => 'New role']);
        $user = User::factory()->create(['name' => 'Before']);
        $user->roles()->attach($old);
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/users/'.$user->public_id, ['name' => 'After', 'password' => 'new-password', 'role_ids' => [$new->id]])
            ->assertOk()->assertJsonPath('data.roles.0.name', 'New role');

        $this->assertDatabaseMissing('role_assignments', ['user_id' => $user->id, 'role_id' => $old->id]);
        $this->assertDatabaseHas('role_assignments', ['user_id' => $user->id, 'role_id' => $new->id]);
        $this->assertAuditSecretsAbsent($user);
    }

    public function test_password_only_update_creates_a_sanitized_user_update_audit(): void
    {
        [$actor] = $this->actorWithPermission('users.update');
        $user = User::factory()->create();
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/users/'.$user->public_id, ['password' => 'new-password'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'auditable_id' => $user->id]);
        $this->assertAuditSecretsAbsent($user);
    }

    public function test_user_can_be_deactivated_without_deleting_the_user(): void
    {
        [$actor] = $this->actorWithPermission('users.delete');
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($actor);

        $this->deleteJson('/api/v1/users/'.$user->public_id)->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'auditable_id' => $user->id]);
        $this->assertAuditSecretsAbsent($user);
    }

    public function test_deactivated_user_can_be_reactivated_with_global_roles(): void
    {
        [$actor] = $this->actorWithPermission('users.update');
        $role = Role::create(['name' => 'Restored role']);
        $user = User::factory()->create(['status' => 'inactive']);
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/users/'.$user->public_id, ['status' => 'active', 'role_ids' => [$role->id]])
            ->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.roles.0.name', 'Restored role');
        $this->assertDatabaseHas('role_assignments', ['user_id' => $user->id, 'role_id' => $role->id]);
    }

    public function test_role_ids_are_validated_against_global_roles(): void
    {
        [$actor] = $this->actorWithPermission('users.create');
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/users', [
            'name' => 'Invalid assignment', 'email' => 'invalid-assignment@example.com', 'password' => 'secret-password', 'role_ids' => [999999],
        ])->assertUnprocessable()->assertJsonValidationErrors(['role_ids.0']);
    }

    public function test_user_crud_requires_the_matching_permission(): void
    {
        [$actor] = $this->actorWithPermission('roles.view');
        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    private function actorWithPermission(string $permissionName): array
    {
        $actor = User::factory()->create();
        $role = Role::create(['name' => 'Permission holder']);
        $role->permissions()->attach(Permission::create(['name' => $permissionName]));
        $role->users()->attach($actor);

        return [$actor];
    }

    private function assertAuditSecretsAbsent(User $user): void
    {
        foreach (AuditLog::where('auditable_id', $user->id)->get() as $log) {
            foreach (['password', 'token', 'access_token'] as $secret) {
                $this->assertArrayNotHasKey($secret, $log->old_values ?? []);
                $this->assertArrayNotHasKey($secret, $log->new_values ?? []);
            }
        }
    }
}
