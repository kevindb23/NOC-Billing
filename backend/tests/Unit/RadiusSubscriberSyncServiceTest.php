<?php

namespace Tests\Unit;

use App\Models\BillingCycle;
use App\Models\BillingAccount;
use App\Models\Bng;
use App\Models\BngRadiusServer;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\SubscriberService;
use App\Models\Subscription;
use App\Services\RadiusSubscriberSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use RuntimeException;
use Tests\TestCase;

class RadiusSubscriberSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_upserts_ppp_subscribers_into_the_external_table(): void
    {
        $external = new PDO('sqlite::memory:');
        $external->exec('CREATE TABLE isp_subscribers (username VARCHAR(64) PRIMARY KEY, status VARCHAR(16) NOT NULL, expires_at DATETIME NULL, plan VARCHAR(64) NOT NULL)');
        $external->exec('CREATE TABLE radcheck (username VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op VARCHAR(2) NOT NULL, value VARCHAR(255) NOT NULL)');
        [$server, $customer] = $this->fixtures();

        $service = new RadiusSubscriberSyncService(fn () => $external);
        $this->assertSame(['synced' => 1], $service->syncServer($server));

        $row = $external->query('SELECT username, status, plan FROM isp_subscribers')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['username' => 'subscriber01', 'status' => 'ACTIVE', 'plan' => 'Home 20'], $row);

        $customer->update(['status' => 'inactive']);
        $service->syncServer($server);
        $this->assertSame('SUSPENDED', $external->query("SELECT status FROM isp_subscribers WHERE username = 'subscriber01'")->fetchColumn());
    }

    public function test_backfill_writes_the_pppoe_password_to_standard_radcheck(): void
    {
        $external = new PDO('sqlite::memory:');
        $external->exec('CREATE TABLE isp_subscribers (username VARCHAR(64) PRIMARY KEY, status VARCHAR(16) NOT NULL, expires_at DATETIME NULL, plan VARCHAR(64) NOT NULL)');
        $external->exec('CREATE TABLE radcheck (username VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op VARCHAR(2) NOT NULL, value VARCHAR(255) NOT NULL)');
        [$server, $customer] = $this->fixtures();

        (new RadiusSubscriberSyncService(fn () => $external))->syncServer($server);

        $row = $external->query("SELECT username, attribute, op, value FROM radcheck WHERE username = 'subscriber01'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame([
            'username' => 'subscriber01',
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
            'value' => 'secret',
        ], $row);
    }

    public function test_active_customer_without_service_is_synced_to_radcheck_immediately(): void
    {
        $external = new PDO('sqlite::memory:');
        $external->exec('CREATE TABLE isp_subscribers (username VARCHAR(64) PRIMARY KEY, status VARCHAR(16) NOT NULL, expires_at DATETIME NULL, plan VARCHAR(64) NOT NULL)');
        $external->exec('CREATE TABLE radcheck (username VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op VARCHAR(2) NOT NULL, value VARCHAR(255) NOT NULL)');
        $bng = Bng::create(['name' => 'Linux BNG', 'vendor' => 'linux', 'model' => 'Accel-PPP', 'management_endpoint' => '10.0.0.10', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $server = BngRadiusServer::create(['bng_id' => $bng->id, 'name' => 'Primary RADIUS', 'server_address' => '10.0.0.20', 'secret' => 'testing123', 'database_name' => 'radius', 'database_username' => 'radius', 'database_password' => 'secret', 'auth_port' => 1812, 'accounting_port' => 1813, 'status' => 'ready', 'sync_subscribers' => true]);
        $customer = Customer::create(['customer_number' => 'CUS-000002', 'customer_type' => 'residential', 'legal_name' => 'New Subscriber', 'ppp_username' => 'new-user', 'ppp_password' => 'new-secret', 'status' => 'active']);

        (new RadiusSubscriberSyncService(fn () => $external))->syncServer($server);

        $this->assertSame('ACTIVE', $external->query("SELECT status FROM isp_subscribers WHERE username = 'new-user'")->fetchColumn());
        $this->assertSame('new-secret', $external->query("SELECT value FROM radcheck WHERE username = 'new-user'")->fetchColumn());
    }

    public function test_inactive_subscriber_cannot_authenticate_from_standard_radcheck(): void
    {
        $external = new PDO('sqlite::memory:');
        $external->exec('CREATE TABLE isp_subscribers (username VARCHAR(64) PRIMARY KEY, status VARCHAR(16) NOT NULL, expires_at DATETIME NULL, plan VARCHAR(64) NOT NULL)');
        $external->exec('CREATE TABLE radcheck (username VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op VARCHAR(2) NOT NULL, value VARCHAR(255) NOT NULL)');
        [$server, $customer] = $this->fixtures();
        $service = new RadiusSubscriberSyncService(fn () => $external);

        $service->syncServer($server);
        $customer->update(['status' => 'inactive']);
        $service->syncServer($server);

        $this->assertFalse((bool) $external->query("SELECT 1 FROM radcheck WHERE username = 'subscriber01' AND attribute = 'Cleartext-Password' LIMIT 1")->fetchColumn());
    }

    public function test_backfill_rejects_a_status_only_radius_schema(): void
    {
        $external = new PDO('sqlite::memory:');
        $external->exec('CREATE TABLE isp_subscribers (username VARCHAR(64) PRIMARY KEY, status VARCHAR(16) NOT NULL, expires_at DATETIME NULL, plan VARCHAR(64) NOT NULL)');
        [$server] = $this->fixtures();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('radcheck table');

        (new RadiusSubscriberSyncService(fn () => $external))->syncServer($server);
    }

    public function test_editing_a_username_removes_the_old_radius_credential(): void
    {
        $external = new PDO('sqlite::memory:');
        $external->exec('CREATE TABLE isp_subscribers (username VARCHAR(64) PRIMARY KEY, status VARCHAR(16) NOT NULL, expires_at DATETIME NULL, plan VARCHAR(64) NOT NULL)');
        $external->exec('CREATE TABLE radcheck (username VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op VARCHAR(2) NOT NULL, value VARCHAR(255) NOT NULL)');
        [$server, $customer] = $this->fixtures();
        $sync = new RadiusSubscriberSyncService(fn () => $external);

        $sync->syncServer($server);
        $customer->update(['ppp_username' => 'subscriber02']);
        $sync->syncEnabledServersForCustomer($customer, 'subscriber01');

        $this->assertFalse((bool) $external->query("SELECT 1 FROM radcheck WHERE username = 'subscriber01' LIMIT 1")->fetchColumn());
        $this->assertTrue((bool) $external->query("SELECT 1 FROM radcheck WHERE username = 'subscriber02' LIMIT 1")->fetchColumn());
    }

    public function test_permanent_customer_cleanup_removes_external_subscriber_and_authentication(): void
    {
        $external = new PDO('sqlite::memory:');
        $external->exec('CREATE TABLE isp_subscribers (username VARCHAR(64) PRIMARY KEY, status VARCHAR(16) NOT NULL, expires_at DATETIME NULL, plan VARCHAR(64) NOT NULL)');
        $external->exec('CREATE TABLE radcheck (username VARCHAR(64) NOT NULL, attribute VARCHAR(64) NOT NULL, op VARCHAR(2) NOT NULL, value VARCHAR(255) NOT NULL)');
        [$server, $customer] = $this->fixtures();
        $sync = new RadiusSubscriberSyncService(fn () => $external);

        $sync->syncServer($server);
        $sync->removeCustomerFromEnabledServers($customer);

        $this->assertFalse((bool) $external->query("SELECT 1 FROM isp_subscribers WHERE username = 'subscriber01' LIMIT 1")->fetchColumn());
        $this->assertFalse((bool) $external->query("SELECT 1 FROM radcheck WHERE username = 'subscriber01' LIMIT 1")->fetchColumn());
    }

    private function fixtures(): array
    {
        $cycle = BillingCycle::create(['name' => 'Monthly', 'interval_unit' => 'month', 'interval_count' => 1, 'billing_day' => 1, 'grace_days' => 7, 'status' => 'active']);
        $plan = Plan::create(['billing_cycle_id' => $cycle->id, 'code' => 'HOME20', 'name' => 'Home 20', 'service_type' => 'internet', 'status' => 'active']);
        $version = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'recurring_price_minor' => 2000, 'setup_fee_minor' => 0, 'currency' => 'PHP', 'download_kbps' => 20000, 'upload_kbps' => 20000, 'effective_from' => '2026-01-01', 'status' => 'active']);
        $bng = Bng::create(['name' => 'Linux BNG', 'vendor' => 'linux', 'model' => 'Accel-PPP', 'management_endpoint' => '10.0.0.10', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $server = BngRadiusServer::create(['bng_id' => $bng->id, 'name' => 'Primary RADIUS', 'server_address' => '10.0.0.20', 'secret' => 'testing123', 'database_name' => 'radius', 'database_username' => 'radius', 'database_password' => 'secret', 'auth_port' => 1812, 'accounting_port' => 1813, 'status' => 'ready', 'sync_subscribers' => true]);
        $customer = Customer::create(['customer_number' => 'CUS-000001', 'customer_type' => 'residential', 'legal_name' => 'Subscriber One', 'ppp_username' => 'subscriber01', 'ppp_password' => 'secret', 'status' => 'active']);
        $account = BillingAccount::create(['customer_id' => $customer->id, 'account_number' => 'BA-000001', 'currency' => 'PHP', 'status' => 'active']);
        $service = SubscriberService::create(['customer_id' => $customer->id, 'billing_account_id' => $account->id, 'service_number' => 'SVC-000001', 'service_type' => 'internet', 'status' => 'active']);
        Subscription::create(['subscriber_service_id' => $service->id, 'billing_account_id' => $account->id, 'plan_version_id' => $version->id, 'status' => 'active', 'starts_on' => '2026-01-01', 'next_billing_date' => '2026-10-01', 'billing_day' => 1, 'price_snapshot_minor' => 2000, 'currency_snapshot' => 'PHP', 'plan_name_snapshot' => $plan->name]);

        return [$server, $customer];
    }
}
