<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_administrator_can_see_users_and_roles(): void
    {
        $this->seed();

        $user = User::where('email', env('SEED_ADMIN_EMAIL', 'admin@example.com'))->firstOrFail();
        $administrator = Role::where('name', 'Administrator')->firstOrFail();

        $this->assertDatabaseHas('role_assignments', [
            'user_id' => $user->id,
            'role_id' => $administrator->id,
        ]);

        $this->assertTrue($administrator->permissions()->whereIn('name', ['users.view', 'roles.view'])->count() === 2);
    }
}
