<?php

namespace Tests\Feature;

use App\Models\Bng;
use App\Models\BngRadiusServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BngRadiusServerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_radius_server_list_serializes_encrypted_credentials_without_a_decrypt_error(): void
    {
        $bng = Bng::create(['name' => 'Linux BNG', 'vendor' => 'linux', 'model' => 'Accel-PPP', 'management_endpoint' => '10.0.0.10', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $server = BngRadiusServer::create(['bng_id' => $bng->id, 'name' => 'Primary RADIUS', 'server_address' => '10.0.0.20', 'secret' => 'testing123', 'database_name' => 'radius', 'database_username' => 'radius', 'database_password' => 'secret', 'auth_port' => 1812, 'accounting_port' => 1813, 'status' => 'ready']);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/v1/bngs/{$bng->public_id}/radius-servers")
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $server->public_id);

        $this->assertNotSame('testing123', DB::table('bng_radius_servers')->where('id', $server->id)->value('secret'));
    }
}
