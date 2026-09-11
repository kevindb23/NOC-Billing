<?php

namespace Tests\Feature;

use App\Models\Organization;
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

    public function test_user_can_use_a_permission_from_a_role_assigned_in_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Billing clerk']);
        $permission = Permission::create(['name' => 'billing.invoices.view']);

        $role->permissions()->attach($permission);
        $role->users()->attach($user, ['organization_id' => $organization->id]);

        $service = app(OrganizationPermissionService::class);

        $this->assertTrue($service->userCan($user, $organization, 'billing.invoices.view'));
        $this->assertSame(['billing.invoices.view'], $service->permissionsFor($user, $organization)->pluck('name')->all());
    }

    public function test_user_cannot_use_a_role_assigned_in_another_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['organization_id' => $otherOrganization->id, 'name' => 'Other organization role']);
        $permission = Permission::create(['name' => 'billing.invoices.view']);

        $role->permissions()->attach($permission);
        $role->users()->attach($user, ['organization_id' => $otherOrganization->id]);

        $service = app(OrganizationPermissionService::class);

        $this->assertFalse($service->userCan($user, $organization, 'billing.invoices.view'));
        $this->assertCount(0, $service->permissionsFor($user, $organization));
    }

    public function test_unknown_permission_is_denied(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse(app(OrganizationPermissionService::class)->userCan($user, $organization, 'does-not-exist'));
    }

    public function test_role_permission_lookup_uses_the_role_permissions_pivot(): void
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

        $seeder->run();
        $firstRun = Permission::query()->orderBy('name')->pluck('name')->all();
        $seeder->run();

        $this->assertSame($firstRun, Permission::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(count($firstRun), Permission::count());
        $this->assertContains('users.*', $firstRun);
        $this->assertContains('roles.*', $firstRun);
        $this->assertContains('audit-logs.view', $firstRun);
        $this->assertContains('dashboard.view', $firstRun);
        $this->assertContains('billing.view', $firstRun);
        $this->assertContains('network.view', $firstRun);
        $this->assertContains('system.view', $firstRun);
    }
}
