<?php

namespace Tests\Feature;

use App\Models\Bng;
use App\Models\BngRadiusServer;
use App\Services\RadiusSubscriberSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use App\Models\User;
use Tests\TestCase;

class RadiusSubscriberSyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_radius_server_persists_the_sync_subscribers_setting(): void
    {
        $bng = $this->bng();
        $server = BngRadiusServer::create([
            'bng_id' => $bng->id,
            'name' => 'Primary RADIUS',
            'server_address' => '10.0.0.20',
            'secret' => 'testing123',
            'database_name' => 'radius',
            'database_username' => 'radius',
            'database_password' => 'secret',
            'auth_port' => 1812,
            'accounting_port' => 1813,
            'status' => 'ready',
            'sync_subscribers' => false,
        ]);

        $response = $this->actingAs(User::factory()->create(), 'sanctum')->patchJson(
            "/api/v1/bngs/{$bng->public_id}/radius-servers/{$server->public_id}",
            [
                'name' => $server->name,
                'server_address' => $server->server_address,
                'database_name' => $server->database_name,
                'database_username' => $server->database_username,
                'auth_port' => 1812,
                'accounting_port' => 1813,
                'sync_subscribers' => false,
                'status' => 'ready',
            ]
        );

        $response->assertOk()->assertJsonPath('data.sync_subscribers', false);
    }

    public function test_new_subscriber_is_pushed_to_enabled_radius_servers(): void
    {
        $external = new PDO('sqlite::memory:');
        $external->exec('CREATE TABLE isp_subscribers (username VARCHAR(64) PRIMARY KEY, status VARCHAR(16) NOT NULL, expires_at DATETIME NULL, plan VARCHAR(64) NOT NULL)');
        $this->radiusServer($this->bng(), true);
        app()->instance(RadiusSubscriberSyncService::class, new RadiusSubscriberSyncService(fn () => $external));

        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/customers', [
            'customer_type' => 'residential',
            'legal_name' => 'New PPP Subscriber',
            'portal_username' => 'portal-user',
            'portal_password' => 'portal-secret',
            'ppp_username' => 'ppp-user',
            'ppp_password' => 'ppp-secret',
            'status' => 'active',
        ])->assertCreated();

        $this->assertSame('ppp-user', $external->query('SELECT username FROM isp_subscribers')->fetchColumn());
    }

    private function bng(): Bng
    {
        return Bng::create(['name' => 'Linux BNG', 'vendor' => 'linux', 'model' => 'Accel-PPP', 'management_endpoint' => '10.0.0.10', 'preferred_transport' => 'ssh', 'status' => 'active']);
    }

    private function radiusServer(Bng $bng, bool $sync): BngRadiusServer
    {
        return BngRadiusServer::create(['bng_id' => $bng->id, 'name' => 'Primary RADIUS', 'server_address' => '10.0.0.20', 'secret' => 'testing123', 'database_name' => 'radius', 'database_username' => 'radius', 'database_password' => 'secret', 'auth_port' => 1812, 'accounting_port' => 1813, 'status' => 'ready', 'sync_subscribers' => $sync]);
    }
}
