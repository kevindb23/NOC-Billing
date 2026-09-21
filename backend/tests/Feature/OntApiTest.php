<?php

namespace Tests\Feature;

use App\Models\Olt;
use App\Models\Onu;
use App\Models\AcsServer;
use App\Services\OltSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class OntApiTest extends TestCase
{
    use CreatesSingleInstallationContext;
    use RefreshDatabase;

    public function test_ont_inventory_is_permission_protected_and_starts_empty(): void
    {
        $user = $this->installationUser(['olts.view'], 'OLT viewer');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/onts')->assertForbidden();

        $user = $this->installationUser(['onts.view'], 'ONT viewer');
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/onts')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_ont_settings_persist_capacity_and_sync_existing_olt_defaults(): void
    {
        $user = $this->installationUser(['onts.view', 'onts.update']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Capacity OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);

        $this->getJson('/api/v1/onts/settings')
            ->assertOk()
            ->assertJsonPath('data.ont_id_capacity_per_port', 64);
        $this->patchJson('/api/v1/onts/settings', ['do_not_allow_rogue_onus' => false, 'ont_id_capacity_per_port' => 128])
            ->assertOk()
            ->assertJsonPath('data.ont_id_capacity_per_port', 128);
        $this->assertSame(128, $olt->fresh()->ont_id_capacity_per_port);
        $this->patchJson('/api/v1/onts/settings', ['do_not_allow_rogue_onus' => false, 'ont_id_capacity_per_port' => 257])
            ->assertUnprocessable();
    }

    public function test_ont_can_be_created_updated_and_deleted_with_duplicate_serial_protection(): void
    {
        $user = $this->installationUser(['onts.view', 'onts.create', 'onts.update', 'onts.delete']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Access OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $payload = ['olt_public_id' => $olt->public_id, 'frame' => 0, 'slot' => 1, 'pon_port' => 2, 'serial_number' => 'HWTC12345678', 'name' => 'Customer ONT', 'status' => 'unknown'];

        $created = $this->postJson('/api/v1/onts', $payload)->assertCreated()->assertJsonPath('data.serial_number', 'HWTC12345678');
        $publicId = $created->json('data.public_id');
        $this->postJson('/api/v1/onts', $payload)->assertUnprocessable()->assertJsonValidationErrors(['serial_number']);
        $this->putJson("/api/v1/onts/{$publicId}", [...$payload, 'name' => 'Updated ONT', 'status' => 'online'])->assertOk()->assertJsonPath('data.name', 'Updated ONT')->assertJsonPath('data.status', 'online');
        $this->deleteJson("/api/v1/onts/{$publicId}")->assertNoContent();
        $this->getJson('/api/v1/onts')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_ont_management_reads_the_matching_genieacs_device(): void
    {
        $user = $this->installationUser(['onts.view']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Access OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $acs = AcsServer::create([
            'name' => 'Primary ACS',
            'api_url' => 'http://acs.example.test:7557',
            'api_username' => 'acs',
            'api_password' => 'secret',
            'transport' => 'cwmp',
            'status' => 'active',
            'ssh_username' => 'root',
            'ssh_password' => 'secret',
            'ssh_port' => 22,
        ]);
        $ont = $olt->onts()->create([
            'acs_server_id' => $acs->id,
            'frame' => 0,
            'slot' => 2,
            'pon_port' => 0,
            'serial_number' => '48575443FAB6E248',
            'name' => 'Living room ONT',
            'status' => 'discovered',
        ]);

        Http::fake(fn ($request) => $request->url() === 'http://acs.example.test:7557/devices/?query=%7B%22_deviceId._SerialNumber%22%3A%2248575443FAB6E248%22%7D'
            ? Http::response([[
                '_id' => 'device-123',
                '_deviceId' => ['_SerialNumber' => '48575443FAB6E248', '_Manufacturer' => 'Huawei', '_ProductClass' => 'HG8245H'],
                '_lastInform' => '2026-09-19T10:00:00.000Z',
                'InternetGatewayDevice' => [
                    'DeviceInfo' => ['ModelName' => ['_value' => 'HG8245H'], 'X_HW_UpPortMode' => ['_value' => 4]],
                    'LANDevice' => [1 => [
                        'WLANConfiguration' => [1 => ['SSID' => ['_value' => 'Home WiFi']]],
                        'LANEthernetInterfaceConfig' => [1 => ['Status' => ['_value' => 'Up']]],
                    ]],
                    'WANDevice' => [1 => [
                        'WANConnectionDevice' => [1 => [
                            'WANPPPConnection' => [1 => [
                                'Username' => ['_value' => 'subscriber01'],
                                'ConnectionStatus' => ['_value' => 'Connected'],
                                'ExternalIPAddress' => ['_value' => '203.0.113.10'],
                                'SubnetMask' => ['_value' => '255.255.255.255'],
                                'DefaultGateway' => ['_value' => '203.0.113.1'],
                                'DNSServers' => ['_value' => '1.1.1.1,8.8.8.8'],
                            ]],
                            'WANIPConnection' => [1 => [
                                'Name' => ['_value' => 'TR069'],
                                'ConnectionStatus' => ['_value' => 'Connected'],
                                'IPAddress' => ['_value' => '192.0.2.20'],
                                'SubnetMask' => ['_value' => '255.255.255.0'],
                                'DefaultGateway' => ['_value' => '192.0.2.1'],
                                'DNSServers' => ['_value' => '192.0.2.1'],
                            ]],
                        ]],
                    ]],
                    'ManagementServer' => [
                        'ConnectionRequestURL' => ['_value' => 'http://192.0.2.20:7547/'],
                    ],
                ],
            ]], 200)
            : Http::response([], 404));

        $this->getJson("/api/v1/onts/{$ont->public_id}/management")
            ->assertOk()
            ->assertJsonPath('data.device_id', 'device-123')
            ->assertJsonPath('data.serial_number', '48575443FAB6E248')
            ->assertJsonPath('data.model', 'HG8245H')
            ->assertJsonPath('data.parameters.wifi_ssid', 'Home WiFi')
            ->assertJsonPath('data.parameters.lan_status', 'Up')
            ->assertJsonPath('data.parameters.upstream_port', 4)
            ->assertJsonPath('data.parameters.wan.internet.0.ip_address', '203.0.113.10')
            ->assertJsonPath('data.parameters.wan.internet.0.pppoe_username', 'subscriber01')
            ->assertJsonPath('data.parameters.wan.internet.0.gateway', '203.0.113.1')
            ->assertJsonPath('data.parameters.wan.tr069.0.ip_address', '192.0.2.20')
            ->assertJsonPath('data.parameters.wan.tr069.0.connection_status', 'Connected')
            ->assertJsonPath('data.parameters.wan.connection_request_url', 'http://192.0.2.20:7547/')
            ->assertJsonPath('data.acs_server.name', 'Primary ACS');
    }

    public function test_ont_configuration_export_requests_and_returns_the_uploaded_hw_ctree_file(): void
    {
        $user = $this->installationUser(['onts.view']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Access OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $acs = AcsServer::create([
            'name' => 'Primary ACS', 'api_url' => 'http://acs.example.test:7557', 'api_username' => 'acs', 'api_password' => 'secret',
            'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'secret', 'ssh_port' => 22,
        ]);
        $ont = $olt->onts()->create([
            'acs_server_id' => $acs->id, 'frame' => 0, 'slot' => 2, 'pon_port' => 0,
            'serial_number' => '48575443FAB6E248', 'name' => 'Living room ONT', 'status' => 'online',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/devices/?query=')) {
                return Http::response([['_id' => 'device-123', '_deviceId' => ['_SerialNumber' => '48575443FAB6E248']]], 200);
            }
            if (str_contains($request->url(), ':7567/device-123/hw_ctree.xml')) {
                return Http::response('<InternetGatewayDevice DBEncrypt="1"><DeviceInfo X_HW_UpPortMode="4"/></InternetGatewayDevice>', 200, ['Content-Type' => 'application/xml']);
            }
            return Http::response(['_id' => 'task-export', 'name' => 'upload'], 200);
        });

        $this->post("/api/v1/onts/{$ont->public_id}/management/configuration-export")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="hw_ctree.xml"')
            ->assertSee('X_HW_UpPortMode="4"', false);

        Http::assertSent(fn ($request) => ($request->data()['name'] ?? null) === 'upload'
            && ($request->data()['fileType'] ?? null) === '3 Vendor Configuration File'
            && ($request->data()['fileName'] ?? null) === 'device-123/hw_ctree.xml');
    }

    public function test_ont_configuration_export_returns_a_json_error_when_genieacs_closes_the_connection(): void
    {
        $user = $this->installationUser(['onts.view']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Access OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $acs = AcsServer::create([
            'name' => 'Primary ACS', 'api_url' => 'http://acs.example.test:7557', 'api_username' => 'acs', 'api_password' => 'secret',
            'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'secret', 'ssh_port' => 22,
        ]);
        $ont = $olt->onts()->create([
            'acs_server_id' => $acs->id, 'frame' => 0, 'slot' => 2, 'pon_port' => 0,
            'serial_number' => '48575443FAB6E248', 'name' => 'Living room ONT', 'status' => 'online',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/devices/?query=')) {
                return Http::response([['_id' => 'device-123', '_deviceId' => ['_SerialNumber' => '48575443FAB6E248']]], 200);
            }

            throw new ConnectionException('cURL error 52: Empty reply from server');
        });

        $this->postJson("/api/v1/onts/{$ont->public_id}/management/configuration-export")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Unable to request the configuration export task. GenieACS closed the connection without returning an HTTP response. Verify that the GenieACS NBI service is running and reachable at http://acs.example.test:7557, then retry.');
    }

    public function test_ont_management_enqueues_supported_genieacs_tasks(): void
    {
        $user = $this->installationUser(['onts.view', 'onts.update']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Access OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $acs = AcsServer::create([
            'name' => 'Primary ACS', 'api_url' => 'http://acs.example.test:7557', 'api_username' => 'acs', 'api_password' => 'secret',
            'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'secret', 'ssh_port' => 22,
        ]);
        $ont = $olt->onts()->create([
            'acs_server_id' => $acs->id, 'frame' => 0, 'slot' => 2, 'pon_port' => 0,
            'serial_number' => '48575443FAB6E248', 'name' => 'Living room ONT', 'status' => 'online',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/devices/?query=')) {
                return Http::response([[
                    '_id' => 'device-123',
                    '_deviceId' => ['_SerialNumber' => '48575443FAB6E248'],
                    'InternetGatewayDevice' => [
                        'LANDevice' => [1 => [
                            'WLANConfiguration' => [
                                1 => ['SSID' => ['_value' => 'Home 24'], 'LowerLayers' => ['_value' => 'InternetGatewayDevice.LANDevice.1.WiFi.Radio.1'], 'KeyPassphrase' => ['_value' => ''], 'PreSharedKey' => [1 => ['KeyPassphrase' => ['_value' => ''], 'PreSharedKey' => ['_value' => '']]]],
                                5 => ['SSID' => ['_value' => 'Home 5'], 'LowerLayers' => ['_value' => 'InternetGatewayDevice.LANDevice.1.WiFi.Radio.2'], 'KeyPassphrase' => ['_value' => ''], 'PreSharedKey' => [1 => ['KeyPassphrase' => ['_value' => ''], 'PreSharedKey' => ['_value' => '']]]],
                            ],
                            'WiFi' => ['Radio' => [
                                1 => ['OperatingFrequencyBand' => ['_value' => '2.4GHz']],
                                2 => ['OperatingFrequencyBand' => ['_value' => '5GHz']],
                            ]],
                        ]],
                        'WANDevice' => [1 => [
                            'WANConnectionDevice' => [1 => [
                                'WANPPPConnection' => [0 => [
                                    'ConnectionStatus' => ['_value' => 'Disconnected'],
                                    'ExternalIPAddress' => ['_value' => '0.0.0.0'],
                                ]],
                            ], 2 => [
                                'WANPPPConnection' => [1 => [
                                    'X_HW_VLAN' => ['_value' => 2000],
                                ]],
                            ]],
                        ]],
                    ],
                ]], 200);
            }
            if (($request['name'] ?? null) === 'refreshObject' && ($request['objectName'] ?? null) === '') {
                return Http::response(['message' => 'task rejected by ACS'], 500);
            }
            return ($request['name'] ?? null) === 'reboot'
                ? Http::response(['_id' => 'task-123', 'name' => 'reboot'], 200)
                : Http::response(['_id' => 'task-queued', 'name' => $request['name'] ?? null], 202);
        });

        $this->postJson("/api/v1/onts/{$ont->public_id}/management/tasks", ['action' => 'reboot'])
            ->assertOk()
            ->assertJsonPath('data.action', 'reboot')
            ->assertJsonPath('data.task.name', 'reboot')
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.execution_status', 'applied')
            ->assertJsonPath('data.task_id', 'task-123');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/devices/device-123/tasks')
            && str_contains($request->url(), 'connection_request')
            && $request['name'] === 'reboot');

        $this->postJson("/api/v1/onts/{$ont->public_id}/management/tasks", [
            'action' => 'update_wifi',
            'wlan' => [
                'band_24' => ['enabled' => true, 'ssid' => 'Home 24', 'password' => 'secret-24', 'hide_ssid' => false, 'auto_channel' => true, 'channel' => null],
                'band_5' => ['enabled' => true, 'ssid' => 'Home 5', 'password' => null, 'hide_ssid' => false, 'auto_channel' => false, 'channel' => 44],
            ],
        ])->assertOk()->assertJsonPath('data.action', 'update_wifi');

        Http::assertSent(fn ($request) => ($request->data()['name'] ?? null) === 'setParameterValues'
            && in_array(['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', 'Home 24', 'xsd:string'], $request->data()['parameterValues'] ?? [], true)
            && in_array(['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey', 'secret-24', 'xsd:string'], $request->data()['parameterValues'] ?? [], true)
            && in_array(['InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Channel', 44, 'xsd:unsignedInt'], $request->data()['parameterValues'] ?? [], true));

        $this->postJson("/api/v1/onts/{$ont->public_id}/management/tasks", [
            'action' => 'configure_pppoe',
            'pppoe_username' => 'subscriber01',
            'pppoe_password' => 'subscriber-secret',
        ])->assertOk()
            ->assertJsonPath('data.action', 'configure_pppoe')
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.execution_status', 'queued')
            ->assertJsonPath('data.task_id', 'task-queued');

        Http::assertSent(fn ($request) => ($request->data()['name'] ?? null) === 'setParameterValues'
            && str_contains($request->url(), 'timeout=30000')
            && in_array(['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.Username', 'subscriber01', 'xsd:string'], $request->data()['parameterValues'] ?? [], true)
            && in_array(['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.Password', 'subscriber-secret', 'xsd:string'], $request->data()['parameterValues'] ?? [], true)
            && in_array(['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.Enable', true, 'xsd:boolean'], $request->data()['parameterValues'] ?? [], true)
            && in_array(['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.ConnectionTrigger', 'AlwaysOn', 'xsd:string'], $request->data()['parameterValues'] ?? [], true));

        $this->postJson("/api/v1/onts/{$ont->public_id}/management/tasks", ['action' => 'refresh_device'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ACS rejected the ONT provisioning task. HTTP 500.');
    }

    public function test_ont_management_can_queue_an_upstream_port_change(): void
    {
        $user = $this->installationUser(['onts.view', 'onts.update']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Access OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $acs = AcsServer::create([
            'name' => 'Primary ACS', 'api_url' => 'http://acs.example.test:7557', 'api_username' => 'acs', 'api_password' => 'secret',
            'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'secret', 'ssh_port' => 22,
        ]);
        $ont = $olt->onts()->create([
            'acs_server_id' => $acs->id, 'frame' => 0, 'slot' => 2, 'pon_port' => 0,
            'serial_number' => '48575443FAB6E248', 'name' => 'Living room ONT', 'status' => 'online',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/devices/?query=')) {
                return Http::response([['_id' => 'device-123', '_deviceId' => ['_SerialNumber' => '48575443FAB6E248']]], 200);
            }
            return Http::response(['_id' => 'task-456', 'name' => 'setParameterValues'], 202);
        });

        $this->postJson("/api/v1/onts/{$ont->public_id}/management/tasks", ['action' => 'update_upstream_port', 'upstream_port' => 'lan3'])
            ->assertOk()
            ->assertJsonPath('data.action', 'update_upstream_port');

        Http::assertSent(fn ($request) => ($request->data()['name'] ?? null) === 'setParameterValues'
            && in_array(['InternetGatewayDevice.DeviceInfo.X_HW_UpPortMode', 3, 'xsd:unsignedInt'], $request->data()['parameterValues'] ?? [], true));
    }

    public function test_discovery_requires_a_connected_olt_session(): void
    {
        $user = $this->installationUser(['onts.discover']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Offline OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $this->mock(OltSessionService::class, function ($mock): void {
            $mock->shouldReceive('discover')->once()->andThrow(new RuntimeException('The OLT is not connected. Start the persistent SSH session first.'));
        });

        $this->postJson('/api/v1/onts/discover', ['olt_public_id' => $olt->public_id])->assertUnprocessable()->assertJsonPath('message', 'The OLT is not connected. Start the persistent SSH session first.');
    }

    public function test_discovery_upserts_by_serial_and_does_not_delete_missing_records(): void
    {
        $user = $this->installationUser(['onts.view', 'onts.create', 'onts.discover']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Discovery OLT', 'vendor' => 'hsgq', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $this->postJson('/api/v1/onts', ['olt_public_id' => $olt->public_id, 'frame' => 0, 'slot' => 1, 'pon_port' => 4, 'serial_number' => 'HWTC12345678', 'name' => 'Existing ONT', 'status' => 'unknown'])->assertCreated();
        $session = Mockery::mock(OltSessionService::class);
        $session->shouldReceive('discover')->twice()->andReturn(
            ['output' => [['frame' => 0, 'slot' => 1, 'pon_port' => 8, 'serial_number' => 'HWTC12345678', 'name' => 'ONT HWTC12345678'], ['frame' => 0, 'slot' => 1, 'pon_port' => 9, 'serial_number' => 'HWTC87654321', 'name' => 'ONT HWTC87654321']]],
            ['output' => [['frame' => 0, 'slot' => 1, 'pon_port' => 8, 'serial_number' => 'HWTC12345678', 'name' => 'ONT HWTC12345678']]],
        );
        $this->app->instance(OltSessionService::class, $session);

        $this->postJson('/api/v1/onts/discover', ['olt_public_id' => $olt->public_id])->assertOk()->assertJsonPath('data.discovered', 1)->assertJsonPath('data.updated', 1);
        $this->postJson('/api/v1/onts/discover', ['olt_public_id' => $olt->public_id])->assertOk()->assertJsonPath('data.discovered', 0)->assertJsonPath('data.updated', 1);
        $this->assertDatabaseCount('onts', 2);
        $this->assertDatabaseHas('onts', ['serial_number' => 'HWTC87654321']);
        $this->assertDatabaseHas('onts', ['serial_number' => 'HWTC12345678', 'pon_port' => 8]);
    }

    public function test_rogue_onu_setting_switches_discovery_status_and_reclassifies_existing_records(): void
    {
        $user = $this->installationUser(['onts.view', 'onts.update', 'onts.discover']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Rogue policy OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $session = Mockery::mock(OltSessionService::class);
        $session->shouldReceive('discover')->twice()->andReturn(
            ['output' => [['frame' => 0, 'slot' => 1, 'pon_port' => 1, 'serial_number' => 'HWTC-ROGUE-1', 'name' => 'ONT HWTC-ROGUE-1']]],
            ['output' => [['frame' => 0, 'slot' => 1, 'pon_port' => 2, 'serial_number' => 'HWTC-DISCOVERED-2', 'name' => 'ONT HWTC-DISCOVERED-2']]],
        );
        $this->app->instance(OltSessionService::class, $session);

        $this->patchJson('/api/v1/onts/settings', ['do_not_allow_rogue_onus' => true])
            ->assertOk()
            ->assertJsonPath('data.do_not_allow_rogue_onus', true);
        $this->postJson('/api/v1/onts/discover', ['olt_public_id' => $olt->public_id])->assertOk();
        $this->assertDatabaseHas('onts', ['serial_number' => 'HWTC-ROGUE-1', 'status' => 'rogue']);

        $this->patchJson('/api/v1/onts/settings', ['do_not_allow_rogue_onus' => false])
            ->assertOk()
            ->assertJsonPath('data.do_not_allow_rogue_onus', false);
        $this->assertDatabaseHas('onts', ['serial_number' => 'HWTC-ROGUE-1', 'status' => 'discovered']);
        $this->postJson('/api/v1/onts/discover', ['olt_public_id' => $olt->public_id])->assertOk();
        $this->assertDatabaseHas('onts', ['serial_number' => 'HWTC-DISCOVERED-2', 'status' => 'discovered']);
    }

    public function test_enabled_rogue_policy_requires_a_serial_from_onu_inventory(): void
    {
        $user = $this->installationUser(['onts.create', 'onts.update']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'Inventory policy OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        Onu::create(['vendor' => 'Huawei', 'model' => 'HG8245H', 'serial_number' => 'HWTC-INVENTORY-1', 'quantity' => 1, 'status' => 'in_stock']);
        $base = ['olt_public_id' => $olt->public_id, 'frame' => 0, 'slot' => 1, 'pon_port' => 1, 'name' => 'Customer ONT', 'status' => 'unknown'];

        $this->patchJson('/api/v1/onts/settings', ['do_not_allow_rogue_onus' => true])->assertOk();
        $this->postJson('/api/v1/onts', [...$base, 'serial_number' => 'NOT-IN-INVENTORY'])->assertUnprocessable()->assertJsonValidationErrors(['serial_number']);
        $this->postJson('/api/v1/onts', [...$base, 'serial_number' => 'HWTC-INVENTORY-1'])->assertCreated();
    }

    public function test_ont_can_be_assigned_to_an_acs_server(): void
    {
        $user = $this->installationUser(['onts.view', 'onts.create']);
        Sanctum::actingAs($user);
        $olt = Olt::create(['name' => 'ACS OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $acs = AcsServer::create(['name' => 'Primary ACS', 'api_url' => 'http://acs:7557', 'api_username' => 'acs', 'api_password' => 'api-secret', 'transport' => 'cwmp', 'status' => 'active', 'ssh_username' => 'root', 'ssh_password' => 'ssh-secret', 'ssh_port' => 22]);

        $created = $this->postJson('/api/v1/onts', [
            'olt_public_id' => $olt->public_id,
            'acs_server_public_id' => $acs->public_id,
            'frame' => 0,
            'slot' => 1,
            'pon_port' => 2,
            'serial_number' => 'HWTC-ACS-1234',
            'name' => 'ACS managed ONT',
            'status' => 'unknown',
        ])->assertCreated();

        $created->assertJsonPath('data.acs_server.public_id', $acs->public_id);
        $this->assertDatabaseHas('onts', ['serial_number' => 'HWTC-ACS-1234', 'acs_server_id' => $acs->id]);
        $this->getJson('/api/v1/onts/acs-servers')->assertOk()->assertJsonPath('data.0.public_id', $acs->public_id);
    }
}
