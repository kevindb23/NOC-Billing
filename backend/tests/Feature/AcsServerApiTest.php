<?php

namespace Tests\Feature;

use App\Models\AcsServer;
use App\Services\AcsServerService;
use App\Services\RouterCommandExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class AcsServerApiTest extends TestCase
{
    use CreatesSingleInstallationContext;
    use RefreshDatabase;

    public function test_acs_inventory_returns_json_unauthorized_response_without_a_session(): void
    {
        $this->get('/api/v1/acs-servers')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_acs_server_crud_is_permission_protected_and_credentials_are_hidden(): void
    {
        $user = $this->installationUser(['acs.view', 'acs.create', 'acs.update', 'acs.delete', 'acs.test']);
        Sanctum::actingAs($user);
        $payload = ['name' => 'ACS1', 'api_url' => 'http://10.0.10.156:7557', 'api_username' => 'acs', 'api_password' => 'api-secret', 'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'ssh-secret', 'ssh_port' => 22];

        $created = $this->postJson('/api/v1/acs-servers', $payload)->assertCreated()->assertJsonMissingPath('data.api_password')->assertJsonMissingPath('data.ssh_password');
        $publicId = $created->json('data.public_id');
        $this->assertDatabaseHas('acs_servers', ['name' => 'ACS1', 'api_username' => 'acs']);
        $this->putJson("/api/v1/acs-servers/{$publicId}", [...$payload, 'name' => 'ACS primary', 'api_password' => '', 'ssh_password' => ''])->assertOk()->assertJsonPath('data.name', 'ACS primary');
        $inventory = $this->getJson('/api/v1/acs-servers')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'ACS primary')
            ->assertJsonMissingPath('data.0.api_password')
            ->assertJsonMissingPath('data.0.ssh_password')
            ->assertHeader('X-Request-ID');
        $this->assertMatchesRegularExpression('/^acs_inventory;dur=\d+(\.\d+)?$/', $inventory->headers->get('Server-Timing'));
        $this->deleteJson("/api/v1/acs-servers/{$publicId}?permanent=1")->assertNoContent();
    }

    public function test_acs_connection_tests_use_saved_credentials_without_returning_them(): void
    {
        $user = $this->installationUser(['acs.view', 'acs.test']);
        Sanctum::actingAs($user);
        $server = AcsServer::create(['name' => 'ACS1', 'api_url' => 'http://10.0.10.156:7557', 'api_username' => 'acs', 'api_password' => 'api-secret', 'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'ssh-secret', 'ssh_port' => 22]);
        $this->mock(AcsServerService::class, function ($mock): void {
            $mock->shouldReceive('testStoredSsh')->once()->andReturn(['message' => 'SSH connection succeeded.']);
            $mock->shouldReceive('testStoredApi')->once()->andReturn(['message' => 'ACS API responded successfully.']);
        });

        $this->postJson("/api/v1/acs-servers/{$server->public_id}/test-ssh")->assertOk()->assertJsonPath('data.message', 'SSH connection succeeded.');
        $this->postJson("/api/v1/acs-servers/{$server->public_id}/test-api")->assertOk()->assertJsonPath('data.message', 'ACS API responded successfully.');
    }

    public function test_genieacs_base_url_probes_the_devices_endpoint(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $service = app(AcsServerService::class);

        $result = $service->testApi([
            'api_url' => 'http://10.0.10.156:7557',
            'api_username' => 'acs',
            'api_password' => 'api-secret',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://10.0.10.156:7557/devices/');
        $this->assertSame('http://10.0.10.156:7557/devices/', $result['url']);
    }

    public function test_password_complexity_settings_read_and_update_through_the_acs_server(): void
    {
        $user = $this->installationUser(['acs.view', 'acs.update']);
        Sanctum::actingAs($user);
        $server = AcsServer::create(['name' => 'ACS1', 'api_url' => 'http://10.0.10.156:7557', 'api_username' => 'acs', 'api_password' => 'api-secret', 'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'ssh-secret', 'ssh_port' => 22]);

        $this->mock(AcsServerService::class, function ($mock) use ($server): void {
            $mock->shouldReceive('getPasswordComplexity')->once()->with(Mockery::on(fn (AcsServer $value): bool => $value->is($server)))->andReturn(['minimum_password_length' => 8]);
            $mock->shouldReceive('updatePasswordComplexity')->once()->with(Mockery::on(fn (AcsServer $value): bool => $value->is($server)), 12)->andReturn(['minimum_password_length' => 12, 'status' => 'applied', 'message' => 'Password complexity updated and GenieACS UI restarted.']);
        });

        $this->getJson("/api/v1/acs-servers/{$server->public_id}/settings/password-complexity")
            ->assertOk()
            ->assertJsonPath('data.minimum_password_length', 8);
        $this->patchJson("/api/v1/acs-servers/{$server->public_id}/settings/password-complexity", ['minimum_password_length' => 12])
            ->assertOk()
            ->assertJsonPath('data.status', 'applied');
    }

    public function test_password_complexity_update_builds_a_privileged_safe_command(): void
    {
        $server = AcsServer::create(['name' => 'ACS1', 'api_url' => 'http://10.0.10.156:7557', 'api_username' => 'acs', 'api_password' => 'api-secret', 'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'ssh-secret', 'ssh_port' => 22]);
        $this->mock(RouterCommandExecutor::class, function ($mock): void {
            $mock->shouldReceive('executeNetmikoShell')->once()->withArgs(function (array $config, string $command): bool {
                return $config['management_endpoint'] === '10.0.10.156:22'
                    && $config['username'] === 'root'
                    && str_contains($command, 'GENIEACS_UI_MIN_PASSWORD_LENGTH=12')
                    && str_contains($command, 'systemctl restart genieacs-ui');
            })->andReturn(['ok' => true, 'output' => 'active']);
        });

        $result = app(AcsServerService::class)->updatePasswordComplexity($server, 12);

        $this->assertSame('applied', $result['status']);
        $this->assertSame(12, $result['minimum_password_length']);
    }
}
