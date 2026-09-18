<?php

namespace Tests\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

trait CreatesSingleInstallationContext
{
    protected function installationUser(array $permissions = [], string $roleName = 'Administrator'): User
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create();
        $role = Role::create(['name' => $roleName, 'guard_name' => 'api']);

        if ($permissions === []) {
            $role->permissions()->sync(Permission::query()->pluck('id'));
        } else {
            $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));
        }

        $role->users()->attach($user);

        return $user;
    }
}
