<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class RouterApiTest extends TestCase
{
    use CreatesSingleInstallationContext;
    use RefreshDatabase;

    public function test_router_inventory_starts_empty_and_has_no_organization_column(): void
    {
        $user = $this->installationUser(['routers.view']);
        Sanctum::actingAs($user);

        $this->assertTrue(Schema::hasTable('routers'));
        $this->assertFalse(Schema::hasColumn('routers', 'organization_id'));

        $this->getJson('/api/v1/routers')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_router_can_be_created_updated_and_deleted(): void
    {
        $user = $this->installationUser(['routers.view', 'routers.create', 'routers.update', 'routers.delete']);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/routers', [
            'name' => 'Core router',
            'vendor' => 'mikrotik',
            'model' => 'RB5009',
            'management_endpoint' => '10.0.0.1',
            'preferred_transport' => 'ssh',
            'username' => 'netadmin',
            'password' => 'router-secret',
            'status' => 'unknown',
        ])->assertCreated();

        $publicId = $created->json('data.public_id');
        $this->assertNotEmpty($publicId);

        $this->putJson("/api/v1/routers/{$publicId}", ['name' => 'Updated router'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated router');

        $this->getJson("/api/v1/routers/{$publicId}")
            ->assertOk()
            ->assertJsonPath('data.username', 'netadmin')
            ->assertJsonPath('data.password', 'router-secret')
            ->assertJsonMissingPath('data.ssh_password');

        $this->deleteJson("/api/v1/routers/{$publicId}")
            ->assertNoContent();

        $this->getJson('/api/v1/routers')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_router_connection_test_uses_the_generic_ssh_endpoint(): void
    {
        $user = $this->installationUser(['routers.view']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/routers/test-connection', [
            'vendor' => 'juniper',
            'management_endpoint' => 'not a valid endpoint',
            'preferred_transport' => 'ssh',
            'username' => 'netadmin',
            'password' => 'secret',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Enter a valid SSH endpoint, such as 10.0.0.1 or 10.0.0.1:22.');
    }
}
