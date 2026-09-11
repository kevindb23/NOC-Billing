<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserRoleAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_role_mutations_are_visible_in_the_organization_audit_log_without_secrets(): void
    {
        [$actor, $organization] = $this->actorWithPermissions(['users.update', 'roles.update']);
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Before']);
        $user = User::factory()->create(['name' => 'Before user']);
        $organization->users()->attach($user, ['status' => 'active']);
        Sanctum::actingAs($actor);

        $this->organizationRequest($organization)->putJson('/api/v1/roles/'.$role->id, [
            'name' => 'After',
        ])->assertOk();
        $this->organizationRequest($organization)->putJson('/api/v1/users/'.$user->public_id, [
            'name' => 'After user',
            'password' => 'new-secret-password',
        ])->assertOk();

        $response = $this->organizationRequest($organization)->getJson('/api/v1/audit-logs?per_page=100');

        $response->assertOk();
        $logs = collect($response->json('data.data'))->keyBy('action');

        $this->assertSame($actor->name, $logs->get('role.updated')['actor']['name']);
        $this->assertSame($organization->id, $logs->get('role.updated')['organization_id']);
        $this->assertSame('Before', $logs->get('role.updated')['old_values']['name']);
        $this->assertSame('After', $logs->get('role.updated')['new_values']['name']);
        $this->assertSame('Before user', $logs->get('user.updated')['old_values']['name']);
        $this->assertSame('After user', $logs->get('user.updated')['new_values']['name']);

        foreach ($logs as $log) {
            $this->assertArrayNotHasKey('password', $log['old_values'] ?? []);
            $this->assertArrayNotHasKey('password', $log['new_values'] ?? []);
            $this->assertArrayNotHasKey('token', $log['old_values'] ?? []);
            $this->assertArrayNotHasKey('token', $log['new_values'] ?? []);
            $this->assertArrayNotHasKey('access_token', $log['old_values'] ?? []);
            $this->assertArrayNotHasKey('access_token', $log['new_values'] ?? []);
        }

        $this->assertStringNotContainsString('new-secret-password', $response->getContent());
    }

    private function actorWithPermissions(array $permissionNames): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Permission holder']);
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::create(['name' => $name]));
        $organization->users()->attach($actor, ['is_default' => true, 'status' => 'active']);
        $role->permissions()->attach($permissions->pluck('id')->all());
        $role->users()->attach($actor, ['organization_id' => $organization->id]);

        return [$actor, $organization];
    }

    private function organizationRequest(Organization $organization)
    {
        return $this->withHeader('X-Organization-Id', $organization->public_id);
    }
}
