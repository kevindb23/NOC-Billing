<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_login_and_receive_a_token(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()->assertJsonStructure(['data' => ['token', 'user', 'organization']]);
    }

    public function test_auth_me_exposes_current_organization_permissions(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Administrator']);
        $permission = Permission::create(['name' => 'users.view']);
        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        $role->permissions()->attach($permission);
        $role->users()->attach($user, ['organization_id' => $organization->id]);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->withHeader('X-Organization-Id', $organization->public_id)->getJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('data.permissions.0', 'users.view');
    }
}
