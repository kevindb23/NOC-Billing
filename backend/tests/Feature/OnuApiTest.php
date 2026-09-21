<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class OnuApiTest extends TestCase
{
    use CreatesSingleInstallationContext;
    use RefreshDatabase;

    public function test_onu_inventory_is_permission_protected_and_starts_empty(): void
    {
        $user = $this->installationUser(['onus.create'], 'ONU creator');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/onus')->assertForbidden();

        $user = $this->installationUser(['onus.view'], 'ONU viewer');
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/onus')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_onu_stock_can_be_created_updated_and_deleted(): void
    {
        $user = $this->installationUser(['onus.view', 'onus.create', 'onus.update', 'onus.delete']);
        Sanctum::actingAs($user);

        $payload = [
            'vendor' => 'Huawei',
            'model' => 'HG8245H',
            'serial_number' => 'HWTC12345678',
            'quantity' => 1,
            'status' => 'in_stock',
            'notes' => 'New warehouse stock',
        ];

        $created = $this->postJson('/api/v1/onus', $payload)
            ->assertCreated()
            ->assertJsonPath('data.vendor', 'Huawei')
            ->assertJsonPath('data.quantity', 1);

        $publicId = $created->json('data.public_id');

        $this->putJson("/api/v1/onus/{$publicId}", [
            ...$payload,
            'quantity' => 4,
            'status' => 'reserved',
        ])->assertOk()->assertJsonPath('data.quantity', 4)->assertJsonPath('data.status', 'reserved');

        $this->deleteJson("/api/v1/onus/{$publicId}")->assertNoContent();
        $this->getJson('/api/v1/onus')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_serial_number_is_optional_for_bulk_stock_but_unique_when_present(): void
    {
        $user = $this->installationUser(['onus.create']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/onus', [
            'vendor' => 'ZTE',
            'model' => 'F601',
            'quantity' => 12,
            'status' => 'in_stock',
        ])->assertCreated();

        $payload = ['vendor' => 'ZTE', 'model' => 'F601', 'serial_number' => 'ZTE123', 'quantity' => 1, 'status' => 'in_stock'];
        $this->postJson('/api/v1/onus', $payload)->assertCreated();
        $this->postJson('/api/v1/onus', $payload)->assertUnprocessable()->assertJsonValidationErrors(['serial_number']);
    }
}
