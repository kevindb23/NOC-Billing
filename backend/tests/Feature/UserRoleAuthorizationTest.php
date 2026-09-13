<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationPermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_use_a_permission_from_an_installation_role(): void
    {
        [$user, $role] = $this->userWithRole('Billing clerk');
        $role->permissions()->attach(Permission::create(['name' => 'billing.invoices.view']));
        $service = app(OrganizationPermissionService::class);

        $this->assertTrue($service->userCan($user, 'billing.invoices.view'));
        $this->assertSame(['billing.invoices.view'], $service->permissionsFor($user)->pluck('name')->all());
    }

    public function test_administrator_has_all_module_access_without_role_permission_rows(): void
    {
        [$user] = $this->userWithRole('Administrator');
        foreach (['users.view', 'roles.view', 'branding.view'] as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        $service = app(OrganizationPermissionService::class);

        $this->assertTrue($service->isSuperAdmin($user));
        $this->assertTrue($service->userCan($user, 'users.view'));
        $this->assertTrue($service->userCan($user, 'roles.view'));
        $this->assertTrue($service->userCan($user, 'branding.view'));
    }

    public function test_installation_roles_are_available_without_organization_memberships(): void
    {
        [$user, $role] = $this->userWithRole('Billing clerk');
        $role->permissions()->attach(Permission::create(['name' => 'billing.invoices.view']));

        $this->assertTrue(app(OrganizationPermissionService::class)->userCan($user, 'billing.invoices.view'));
    }

    public function test_unknown_permission_is_denied(): void
    {
        $this->assertFalse(app(OrganizationPermissionService::class)->userCan(User::factory()->create(), 'does-not-exist'));
    }

    public function test_role_permission_lookup_uses_the_global_role_permissions_pivot(): void
    {
        $role = Role::create(['name' => 'Auditor']);
        $permission = Permission::create(['name' => 'audit-logs.view']);
        $role->permissions()->attach($permission);

        $this->assertTrue($role->permissions->contains($permission));
        $this->assertSame('audit-logs.view', $role->permissions()->first()->name);
    }

    public function test_permission_catalog_seeder_is_idempotent(): void
    {
        $seeder = new PermissionSeeder();
        Permission::create(['name' => 'users.*']);
        $seeder->run();
        $firstRun = Permission::query()->orderBy('name')->pluck('name')->all();
        $seeder->run();

        $this->assertSame($firstRun, Permission::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(count($firstRun), Permission::count());
    }

    private function userWithRole(string $name): array
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => $name]);
        $user->roles()->attach($role);

        return [$user, $role];
    }
}
