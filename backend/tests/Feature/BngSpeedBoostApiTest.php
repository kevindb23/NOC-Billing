<?php

namespace Tests\Feature;

use App\Models\Bng;
use App\Models\BillingCycle;
use App\Models\BillingAccount;
use App\Models\Customer;
use App\Models\BngRadiusServer;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\SubscriberService;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BngSpeedBoostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_speed_boost_preview_and_draft_save_use_native_filter_id(): void
    {
        $user = User::factory()->create();
        [$bng, $radius, $plan] = $this->fixtures();
        $api = $this->actingAs($user, 'sanctum');

        $api->postJson("/api/v1/bngs/{$bng->public_id}/speed-boosts/preview", [
            'radius_server_id' => $radius->public_id,
            'plan_id' => $plan->id,
            'download_mbps' => 20,
            'upload_mbps' => 20,
        ])->assertOk()->assertJsonPath('data.rate_value', '20000/20000');

        $api->postJson("/api/v1/bngs/{$bng->public_id}/speed-boosts", [
            'radius_server_id' => $radius->public_id,
            'plan_id' => $plan->id,
            'download_mbps' => 20,
            'upload_mbps' => 20,
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('bng_speed_boosts', [
            'bng_id' => $bng->id,
            'bng_radius_server_id' => $radius->id,
            'plan_id' => $plan->id,
            'download_kbps' => 20000,
            'upload_kbps' => 20000,
            'status' => 'draft',
        ]);
    }

    public function test_only_one_speed_boost_is_allowed_for_a_plan_and_radius_server(): void
    {
        $user = User::factory()->create();
        [$bng, $radius, $plan] = $this->fixtures();
        $payload = ['radius_server_id' => $radius->public_id, 'plan_id' => $plan->id, 'download_mbps' => 20, 'upload_mbps' => 20];
        $api = $this->actingAs($user, 'sanctum');

        $api->postJson("/api/v1/bngs/{$bng->public_id}/speed-boosts", $payload)->assertCreated();
        $api->postJson("/api/v1/bngs/{$bng->public_id}/speed-boosts", $payload)->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    }

    public function test_preview_resolves_active_ppp_users_from_the_selected_plan(): void
    {
        $user = User::factory()->create();
        [$bng, $radius, $plan] = $this->fixtures();
        $customer = Customer::create(['customer_number' => 'CUS-000001', 'customer_type' => 'residential', 'legal_name' => 'Subscriber One', 'ppp_username' => 'subscriber01', 'ppp_password' => 'secret', 'status' => 'active']);
        $account = BillingAccount::create(['customer_id' => $customer->id, 'account_number' => 'BA-000001', 'currency' => 'PHP', 'status' => 'active']);
        $service = SubscriberService::create(['customer_id' => $customer->id, 'billing_account_id' => $account->id, 'service_number' => 'SVC-000001', 'service_type' => 'internet', 'status' => 'active']);
        $version = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'recurring_price_minor' => 2000, 'setup_fee_minor' => 0, 'currency' => 'PHP', 'download_kbps' => 20000, 'upload_kbps' => 20000, 'effective_from' => '2026-01-01', 'status' => 'active']);
        Subscription::create(['subscriber_service_id' => $service->id, 'billing_account_id' => $account->id, 'plan_version_id' => $version->id, 'status' => 'active', 'starts_on' => '2026-01-01', 'next_billing_date' => '2026-10-01', 'billing_day' => 1, 'price_snapshot_minor' => 2000, 'currency_snapshot' => 'PHP', 'plan_name_snapshot' => $plan->name]);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/bngs/{$bng->public_id}/speed-boosts/preview", [
            'radius_server_id' => $radius->public_id,
            'plan_id' => $plan->id,
            'download_mbps' => 20,
            'upload_mbps' => 20,
        ])->assertOk()->assertJsonPath('data.usernames.0', 'subscriber01')->assertJsonPath('data.sql.1', "INSERT INTO radreply (username, attribute, op, value) VALUES ('subscriber01', 'Filter-Id', ':=', '20000/20000');");
    }

    private function fixtures(): array
    {
        $cycle = BillingCycle::create(['name' => 'Monthly', 'interval_unit' => 'month', 'interval_count' => 1, 'billing_day' => 1, 'grace_days' => 7, 'status' => 'active']);
        $plan = Plan::create(['billing_cycle_id' => $cycle->id, 'code' => 'HOME20', 'name' => 'Home 20', 'service_type' => 'internet', 'status' => 'active']);
        $bng = Bng::create(['name' => 'Linux BNG', 'vendor' => 'linux', 'model' => 'Accel-PPP', 'management_endpoint' => '10.0.0.10', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $radius = BngRadiusServer::create(['bng_id' => $bng->id, 'name' => 'Primary RADIUS', 'server_address' => '10.0.0.20', 'secret' => 'testing123', 'database_name' => 'radius', 'database_username' => 'radius', 'database_password' => 'secret', 'auth_port' => 1812, 'accounting_port' => 1813, 'status' => 'ready']);

        return [$bng, $radius, $plan];
    }
}
