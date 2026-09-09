<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class BillingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_created_through_the_tenant_api(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/customers', [
            'customer_type' => 'residential',
            'legal_name' => 'Acme Household',
            'email' => 'customer@example.test',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('customers', [
            'legal_name' => 'Acme Household',
            'email' => 'customer@example.test',
        ]);
    }

    public function test_customer_can_be_updated_and_archived(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        $customer = \App\Models\Customer::create([
            'organization_id' => $organization->id,
            'customer_number' => 'CUS-000001',
            'customer_type' => 'residential',
            'legal_name' => 'Before Update',
        ]);

        $api = $this->actingAs($user, 'sanctum');
        $api->putJson('/api/v1/customers/'.$customer->public_id, ['legal_name' => 'After Update'])->assertOk();
        $api->deleteJson('/api/v1/customers/'.$customer->public_id)->assertNoContent();

        $this->assertSoftDeleted('customers', ['id' => $customer->id, 'legal_name' => 'After Update']);
    }

    public function test_customer_from_another_organization_is_not_visible(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $user = User::factory()->create();
        $first->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        $foreign = \App\Models\Customer::create([
            'organization_id' => $second->id,
            'customer_number' => 'CUS-000001',
            'customer_type' => 'residential',
            'legal_name' => 'Private Customer',
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/customers/'.$foreign->public_id)->assertNotFound();
    }
}
