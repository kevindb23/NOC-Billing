<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class OltApiTest extends TestCase
{
    use CreatesSingleInstallationContext;
    use RefreshDatabase;

    public function test_olt_inventory_is_permission_protected_and_starts_empty(): void
    {
        $user = $this->installationUser(['olts.view']);
        Sanctum::actingAs($user);

        $this->assertTrue(Schema::hasTable('olts'));
        $this->assertFalse(Schema::hasColumn('olts', 'organization_id'));
        $this->getJson('/api/v1/olts')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_olt_can_be_created_updated_archived_and_permanently_deleted(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.create', 'olts.update', 'olts.delete']);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/olts', [
            'name' => 'Access OLT', 'vendor' => 'huawei', 'model' => 'MA5800',
            'management_endpoint' => '10.0.0.20', 'preferred_transport' => 'ssh', 'status' => 'active', 'notes' => 'Core access shelf',
        ])->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->putJson("/api/v1/olts/{$publicId}", ['vendor' => 'zte', 'preferred_transport' => 'netconf'])->assertOk()->assertJsonPath('data.preferred_transport', 'netconf');
        $this->deleteJson("/api/v1/olts/{$publicId}")->assertNoContent();
        $this->deleteJson("/api/v1/olts/{$publicId}?permanent=1")->assertNoContent();
        $this->getJson('/api/v1/olts')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_olt_connection_test_validates_the_generic_transport_request(): void
    {
        $user = $this->installationUser(['olts.view']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/olts/test-connection', [
            'vendor' => 'huawei',
            'management_endpoint' => 'not a valid endpoint',
            'preferred_transport' => 'api',
        ])->assertUnprocessable()->assertJsonPath('message', 'Enter a valid OLT management endpoint.');
    }
}
