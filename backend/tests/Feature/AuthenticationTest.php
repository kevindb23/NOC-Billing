<?php

namespace Tests\Feature;

use App\Models\Organization;
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
}
