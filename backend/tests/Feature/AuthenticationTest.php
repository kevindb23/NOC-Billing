<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSingleInstallationContext;

    public function test_operator_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()->assertJsonStructure(['data' => ['token', 'user', 'permissions', 'is_superadmin', 'branding']]);
    }

    public function test_auth_me_exposes_installation_permissions(): void
    {
        $user = $this->installationUser(['users.view'], 'Administrator');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('data.is_superadmin', true);
        $this->assertContains('users.view', $response->json('data.permissions'));
    }
}
