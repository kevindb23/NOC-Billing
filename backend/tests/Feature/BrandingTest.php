<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_permissions_are_seeded_and_grouped_under_system(): void
    {
        (new PermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'branding.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'branding.update']);
    }

    public function test_authorized_user_can_read_and_update_organization_branding(): void
    {
        [$user, $organization] = $this->authorizedContext();
        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', $organization->public_id)
            ->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('data.branding.organization_name', $organization->name);

        $response = $this->withHeader('X-Organization-Id', $organization->public_id)->putJson('/api/v1/branding', [
            'organization_name' => 'Acme Fiber',
            'short_name' => 'Acme',
            'brand_mark' => 'AF',
            'tagline' => 'Connected locally',
            'logo_url' => 'https://example.com/acme.svg',
            'primary_color' => '#2f8f46',
            'accent_color' => '#e8f4e8',
        ]);

        $response->assertOk()->assertJsonPath('data.branding.short_name', 'Acme');
        $this->assertDatabaseHas('organization_brandings', ['organization_id' => $organization->id, 'brand_mark' => 'AF']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'action' => 'branding.updated']);
    }

    public function test_branding_is_tenant_scoped_and_requires_permissions(): void
    {
        [$user, $organization] = $this->authorizedContext();
        $otherOrganization = Organization::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', $otherOrganization->public_id)
            ->getJson('/api/v1/branding')
            ->assertForbidden();

        $viewer = User::factory()->create();
        $organization->users()->attach($viewer, ['is_default' => false, 'status' => 'active']);
        Sanctum::actingAs($viewer);

        $this->withHeader('X-Organization-Id', $organization->public_id)
            ->getJson('/api/v1/branding')
            ->assertForbidden();
    }

    public function test_branding_validates_colors_and_logo_urls(): void
    {
        [$user, $organization] = $this->authorizedContext();
        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', $organization->public_id)
            ->putJson('/api/v1/branding', ['logo_url' => 'javascript:alert(1)', 'primary_color' => 'green'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo_url', 'primary_color']);
    }

    public function test_login_uses_default_branding_when_branding_table_is_not_available(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        Schema::drop('organization_brandings');

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()->assertJsonPath('data.branding.organization_name', $organization->name);
    }

    private function authorizedContext(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'Brand manager']);
        $permissions = Permission::query()->updateOrCreate(['name' => 'branding.view'], ['guard_name' => 'api'])->id;
        $updatePermission = Permission::query()->updateOrCreate(['name' => 'branding.update'], ['guard_name' => 'api']);
        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        $role->permissions()->attach([$permissions, $updatePermission->id]);
        $role->users()->attach($user, ['organization_id' => $organization->id]);

        return [$user, $organization];
    }
}
