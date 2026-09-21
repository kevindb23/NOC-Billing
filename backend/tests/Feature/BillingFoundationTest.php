<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\BillingAccount;
use App\Models\User;
use App\Models\SubscriberService;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class BillingFoundationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSingleInstallationContext;

    public function test_customer_can_be_created_through_the_installation_api(): void
    {
        $user = User::factory()->create();

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
        $user = User::factory()->create();
        $customer = \App\Models\Customer::create([
            'customer_number' => 'CUS-000001',
            'customer_type' => 'residential',
            'legal_name' => 'Before Update',
        ]);

        $api = $this->actingAs($user, 'sanctum');
        $api->putJson('/api/v1/customers/'.$customer->public_id, ['legal_name' => 'After Update'])->assertOk();
        $api->deleteJson('/api/v1/customers/'.$customer->public_id)->assertNoContent();

        $this->assertSoftDeleted('customers', ['id' => $customer->id, 'legal_name' => 'After Update']);
    }

    public function test_customer_ppp_credentials_are_persisted_for_acs_activation(): void
    {
        $user = $this->installationUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/customers', [
            'customer_type' => 'residential',
            'legal_name' => 'ACS Subscriber',
            'portal_username' => 'portal-user',
            'portal_password' => 'portal-secret',
            'ppp_username' => 'ppp-user',
            'ppp_password' => 'ppp-secret',
            'status' => 'active',
        ])->assertCreated();

        $customer = \App\Models\Customer::query()->where('public_id', $response->json('data.public_id'))->firstOrFail();
        $this->assertSame('ppp-user', $customer->ppp_username);
        $this->assertSame('ppp-secret', $customer->ppp_password);
        $this->assertTrue($customer->hasPppCredentials());
        $response->assertJsonPath('data.ppp_credentials_configured', true);
    }

    public function test_customer_from_the_installation_is_visible(): void
    {
        $user = User::factory()->create();
        $customer = \App\Models\Customer::create([
            'customer_number' => 'CUS-000001',
            'customer_type' => 'residential',
            'legal_name' => 'Private Customer',
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/customers/'.$customer->public_id)->assertOk();
    }

    public function test_empty_subscriber_setup_can_be_permanently_deleted(): void
    {
        $user = User::factory()->create();
        $customer = \App\Models\Customer::create([
            'customer_number' => 'CUS-000001',
            'customer_type' => 'residential',
            'legal_name' => 'Empty Subscriber',
        ]);
        $account = BillingAccount::create([
            'customer_id' => $customer->id,
            'account_number' => 'BA-000001',
            'currency' => 'PHP',
            'status' => 'active',
        ]);
        SubscriberService::create([
            'customer_id' => $customer->id,
            'billing_account_id' => $account->id,
            'service_number' => 'SVC-000001',
            'service_type' => 'internet',
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/customers/'.$customer->public_id.'?permanent=1')
            ->assertNoContent();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('billing_accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('subscriber_services', ['customer_id' => $customer->id]);
    }

    public function test_unreferenced_billing_account_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $customer = \App\Models\Customer::create([
            'customer_number' => 'CUS-000001',
            'customer_type' => 'residential',
            'legal_name' => 'Account Owner',
        ]);
        $account = BillingAccount::create([
            'customer_id' => $customer->id,
            'account_number' => 'BA-000001',
            'currency' => 'PHP',
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/billing-accounts/'.$account->public_id)
            ->assertNoContent();

        $this->assertDatabaseMissing('billing_accounts', ['id' => $account->id]);
    }
    public function test_saving_an_active_subscriber_with_a_ppp_username_activates_pending_services(): void
    {
        $user = $this->installationUser();
        $customer = \App\Models\Customer::create([
            'customer_number' => 'CUS-000004', 'customer_type' => 'residential',
            'legal_name' => 'PPP Customer', 'status' => 'active', 'ppp_username' => 'ppp-customer',
        ]);
        $api = $this->actingAs($user, 'sanctum');
        $account = $api->postJson('/api/v1/billing-accounts', ['customer_id' => $customer->public_id])->assertCreated()->json('data');
        $service = $api->postJson('/api/v1/subscriber-services', [
            'customer_id' => $customer->public_id, 'billing_account_id' => $account['public_id'],
        ])->assertCreated()->json('data');

        $api->putJson('/api/v1/customers/'.$customer->public_id, [
            'status' => 'active', 'ppp_username' => 'ppp-customer',
        ])->assertOk();

        $this->assertDatabaseHas('subscriber_services', [
            'public_id' => $service['public_id'], 'status' => 'active',
        ]);
    }
}
