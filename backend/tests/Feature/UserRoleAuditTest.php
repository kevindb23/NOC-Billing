<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserRoleAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_role_mutations_are_visible_in_the_installation_audit_log_without_secrets(): void
    {
        [$actor] = $this->actorWithPermissions(['users.update', 'roles.update']);
        $role = Role::create(['name' => 'Before']);
        $user = User::factory()->create(['name' => 'Before user']);
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/roles/'.$role->id, ['name' => 'After'])->assertOk();
        $this->putJson('/api/v1/users/'.$user->public_id, ['name' => 'After user', 'password' => 'new-secret-password'])->assertOk();

        $response = $this->getJson('/api/v1/audit-logs?per_page=100')->assertOk();
        $logs = collect($response->json('data.data'))->keyBy('action');

        $this->assertSame($actor->name, $logs->get('role.updated')['actor']['name']);
        $this->assertArrayNotHasKey('organization_id', $logs->get('role.updated'));
        $this->assertSame('Before', $logs->get('role.updated')['old_values']['name']);
        $this->assertSame('After', $logs->get('role.updated')['new_values']['name']);
        $this->assertSame('Before user', $logs->get('user.updated')['old_values']['name']);
        $this->assertSame('After user', $logs->get('user.updated')['new_values']['name']);

        foreach ($logs as $log) {
            foreach (['password', 'token', 'access_token'] as $secret) {
                $this->assertArrayNotHasKey($secret, $log['old_values'] ?? []);
                $this->assertArrayNotHasKey($secret, $log['new_values'] ?? []);
            }
        }
        $this->assertStringNotContainsString('new-secret-password', $response->getContent());
    }

    private function actorWithPermissions(array $permissionNames): array
    {
        $actor = User::factory()->create();
        $role = Role::create(['name' => 'Permission holder']);
        foreach ($permissionNames as $name) {
            $role->permissions()->attach(Permission::create(['name' => $name]));
        }
        $role->users()->attach($actor);

        return [$actor];
    }
}
