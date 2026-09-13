<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSingleInstallationContext;

    public function test_branding_permissions_are_seeded_and_grouped_under_system(): void
    {
        (new PermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'branding.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'branding.update']);
    }

    public function test_authorized_user_can_read_and_update_installation_branding(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('data.branding.organization_name', config('app.name'));

        $response = $this->putJson('/api/v1/branding', [
            'organization_name' => 'Acme Fiber',
            'short_name' => 'Acme',
            'brand_mark' => 'AF',
            'tagline' => 'Connected locally',
            'logo_url' => 'https://example.com/acme.svg',
            'primary_color' => '#2f8f46',
            'accent_color' => '#e8f4e8',
        ]);

        $response->assertOk()->assertJsonPath('data.branding.short_name', 'Acme');
        $this->assertDatabaseHas('organization_brandings', ['brand_mark' => 'AF']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branding.updated']);
    }

    public function test_branding_is_installation_wide_and_requires_permissions(): void
    {
        $this->authorizedContext();
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/branding')
            ->assertForbidden();
    }

    public function test_branding_validates_colors_and_logo_urls(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/branding', ['logo_url' => 'javascript:alert(1)', 'primary_color' => 'green'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo_url', 'primary_color']);
    }

    public function test_login_uses_default_branding_when_branding_table_is_not_available(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        Schema::drop('organization_brandings');

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()->assertJsonPath('data.branding.organization_name', config('app.name'));
    }

    private function authorizedContext(): User
    {
        return $this->installationUser(['branding.view', 'branding.update'], 'Brand manager');
    }
}
