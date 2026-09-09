<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_invoice_and_payment_workflow_updates_the_invoice_balance(): void
    {
        [$organization, $user] = $this->tenant();
        $cycle = BillingCycle::create(['organization_id' => $organization->id, 'name' => 'Monthly', 'interval_unit' => 'month']);
        $plan = Plan::create(['organization_id' => $organization->id, 'billing_cycle_id' => $cycle->id, 'code' => 'HOME-100', 'name' => 'Home 100', 'service_type' => 'internet']);
        $version = PlanVersion::create([
            'organization_id' => $organization->id, 'plan_id' => $plan->id, 'version' => 1,
            'recurring_price_minor' => 150000, 'setup_fee_minor' => 0, 'currency' => 'PHP',
            'download_kbps' => 100000, 'upload_kbps' => 50000, 'effective_from' => '2026-01-01',
        ]);
        $customer = Customer::create(['organization_id' => $organization->id, 'customer_number' => 'CUS-000001', 'customer_type' => 'residential', 'legal_name' => 'Billing Customer']);

        $api = $this->actingAs($user, 'sanctum');
        $account = $api->postJson('/api/v1/billing-accounts', ['customer_id' => $customer->public_id])->assertCreated()->json('data');
        $service = $api->postJson('/api/v1/subscriber-services', [
            'customer_id' => $customer->public_id, 'billing_account_id' => $account['public_id'], 'service_type' => 'internet',
        ])->assertCreated()->json('data');
        $subscription = $api->postJson('/api/v1/subscriptions', [
            'subscriber_service_id' => $service['public_id'], 'billing_account_id' => $account['public_id'], 'plan_version_id' => $version->id,
            'starts_on' => '2026-09-01', 'next_billing_date' => '2026-09-01',
        ])->assertCreated()->json('data');
        $invoice = $api->postJson('/api/v1/invoices', [
            'billing_account_id' => $account['public_id'], 'subscription_id' => $subscription['id'],
            'issue_date' => '2026-09-01', 'due_date' => '2026-09-15',
        ])->assertCreated()->json('data');

        $api->postJson('/api/v1/payments', [
            'billing_account_id' => $account['public_id'], 'invoice_id' => $invoice['public_id'],
            'amount_minor' => 150000, 'payment_method' => 'bank_transfer', 'reference' => 'TX-1001',
        ])->assertCreated();

        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'status' => 'paid', 'balance_due_minor' => 0]);
        $this->assertDatabaseHas('payment_allocations', ['invoice_id' => $invoice['id'], 'amount_minor' => 150000]);
    }

    private function tenant(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user, ['is_default' => true, 'status' => 'active']);
        return [$organization, $user];
    }
}
