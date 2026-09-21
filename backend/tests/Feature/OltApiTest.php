<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use App\Services\OltProvisioningService;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class OltApiTest extends TestCase
{
    use CreatesSingleInstallationContext;
    use RefreshDatabase;

    public function test_olt_inventory_is_permission_protected_and_starts_empty(): void
    {
        $user = $this->installationUser(['olts.view']);
        Sanctum::actingAs($user);

        $this->assertTrue(Schema::hasTable('olts'));
        $this->assertFalse(Schema::hasColumn('olts', 'organization_id'));
        $this->getJson('/api/v1/olts')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_olt_can_be_created_updated_archived_and_permanently_deleted(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.create', 'olts.update', 'olts.delete']);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/olts', [
            'name' => 'Access OLT', 'vendor' => 'huawei', 'model' => 'MA5800',
            'management_endpoint' => '10.0.0.20', 'preferred_transport' => 'ssh', 'status' => 'active', 'notes' => 'Core access shelf',
        ])->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->putJson("/api/v1/olts/{$publicId}", ['vendor' => 'zte', 'preferred_transport' => 'netconf'])->assertOk()->assertJsonPath('data.preferred_transport', 'netconf');
        $this->deleteJson("/api/v1/olts/{$publicId}")->assertNoContent();
        $this->deleteJson("/api/v1/olts/{$publicId}?permanent=1")->assertNoContent();
        $this->getJson('/api/v1/olts')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_olt_connection_test_validates_the_generic_transport_request(): void
    {
        $user = $this->installationUser(['olts.view']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/olts/test-connection', [
            'vendor' => 'huawei',
            'management_endpoint' => 'not a valid endpoint',
            'preferred_transport' => 'api',
        ])->assertUnprocessable()->assertJsonPath('message', 'Enter a valid OLT management endpoint.');
    }

    public function test_hsgq_is_an_accepted_olt_vendor(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.create']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/olts', [
            'name' => 'HSGQ Access OLT', 'vendor' => 'hsgq', 'model' => 'G08L',
            'management_endpoint' => '10.0.0.21', 'preferred_transport' => 'ssh', 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.vendor', 'hsgq');
    }

    public function test_olt_vlan_range_persists_the_selected_type_and_end_vlan(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.create', 'olts.provision']);
        Sanctum::actingAs($user);

        $olt = $this->postJson('/api/v1/olts', [
            'name' => 'Range OLT', 'vendor' => 'huawei', 'model' => 'MA5800',
            'management_endpoint' => '10.0.0.22', 'preferred_transport' => 'ssh', 'status' => 'active',
        ])->assertCreated();
        $publicId = $olt->json('data.public_id');

        $this->postJson("/api/v1/olts/{$publicId}/vlans", [
            'name' => 'Access VLANs', 'vlan_id' => 35, 'vlan_to' => 40, 'vlan_type' => 'to', 'service_mode' => 'internet',
        ])->assertCreated()->assertJsonPath('data.vlan_id', 35)->assertJsonPath('data.vlan_to', 40)->assertJsonPath('data.vlan_type', 'to');
    }

    public function test_ont_line_profile_persists_management_and_encryption_settings(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.create', 'olts.provision']);
        Sanctum::actingAs($user);
        $olt = \App\Models\Olt::create(['name' => 'Line Profile OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active', 'ont_line_profile_start_id' => 1]);

        $this->mock(OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('createQinq')->once()->withArgs(function ($olt, array $values): bool {
                return $values['qinq_type'] === 'ont_line_profile'
                    && $values['profile_id'] === 1
                    && $values['tr069_management_enabled'] === false
                    && $values['tr069_ip_index'] === 3
                    && $values['omcc_encrypt_enabled'] === false;
            })->andReturn(['status' => 'applied', 'message' => 'Line profile applied.']);
        });

        $response = $this->postJson("/api/v1/olts/{$olt->public_id}/qinq", [
            'qinq_type' => 'ont_line_profile',
            'outer_vlan' => 3001,
            'inner_vlan' => 25,
            'dba_profile_id' => 15,
            'tr069_management_enabled' => false,
            'tr069_ip_index' => 3,
            'omcc_encrypt_enabled' => false,
        ])->assertCreated()->assertJsonPath('data.tr069_management_enabled', false)->assertJsonPath('data.tr069_ip_index', 3)->assertJsonPath('data.omcc_encrypt_enabled', false);

        $this->assertDatabaseHas('olt_qinq_provisions', [
            'id' => $response->json('data.id'),
            'tr069_management_enabled' => false,
            'tr069_ip_index' => 3,
            'omcc_encrypt_enabled' => false,
        ]);
    }

    public function test_ont_line_profile_preview_returns_commands_without_creating_a_record(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.create', 'olts.provision']);
        Sanctum::actingAs($user);
        $olt = \App\Models\Olt::create(['name' => 'Preview OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active', 'ont_line_profile_start_id' => 1]);

        $this->mock(OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('previewQinq')->once()->withArgs(function ($olt, array $values): bool {
                return $values['profile_id'] === 1
                    && $values['profile_name'] === 'LP_CVLAN_3001'
                    && $values['tr069_management_enabled'] === true
                    && $values['tr069_ip_index'] === 1
                    && $values['omcc_encrypt_enabled'] === true;
        })->andReturn(['commands' => ['config', 'ont-lineprofile gpon profile-id 1 profile-name "LP_CVLAN_3001"', 'save']]);
        });

        $this->postJson("/api/v1/olts/{$olt->public_id}/qinq/preview", [
            'qinq_type' => 'ont_line_profile',
            'outer_vlan' => 3001,
            'inner_vlan' => 25,
            'dba_profile_id' => 15,
        ])->assertOk()->assertJsonPath('data.commands.1', 'ont-lineprofile gpon profile-id 1 profile-name "LP_CVLAN_3001"');

        $this->assertDatabaseCount('olt_qinq_provisions', 0);
    }

    public function test_ont_line_profile_driver_payload_accepts_preview_profile_name(): void
    {
        $payload = (new \App\Services\HuaweiOltDriver())->provisioningPayload('ont_line_profile', [
            'profile_id' => 17,
            'profile_name' => 'LP_CVLAN_200',
            'dba_profile_id' => 15,
            'outer_vlan' => 200,
            'inner_vlan' => 25,
        ]);

        $this->assertSame('LP_CVLAN_200', $payload['values']['profile_name']);
    }

    public function test_ont_id_capacity_can_be_configured_per_olt(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.create', 'olts.provision']);
        Sanctum::actingAs($user);
        $olt = \App\Models\Olt::create(['name' => 'Capacity OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);

        $this->assertSame(64, $olt->fresh()->ont_id_capacity_per_port);
        $this->patchJson("/api/v1/olts/{$olt->public_id}/ont-id-capacity-settings", ['ont_id_capacity_per_port' => 64])
            ->assertOk()
            ->assertJsonPath('data.ont_id_capacity_per_port', 64);
        $this->assertSame(64, $olt->fresh()->ont_id_capacity_per_port);
        $this->patchJson("/api/v1/olts/{$olt->public_id}/ont-id-capacity-settings", ['ont_id_capacity_per_port' => 257])
            ->assertUnprocessable();
    }

    public function test_olt_tr069_server_profile_can_be_created_without_returning_its_password(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.create', 'olts.provision']);
        Sanctum::actingAs($user);

        $olt = $this->postJson('/api/v1/olts', [
            'name' => 'TR-069 OLT', 'vendor' => 'huawei', 'model' => 'MA5800',
            'management_endpoint' => '10.0.0.23', 'preferred_transport' => 'ssh', 'status' => 'active',
        ])->assertCreated();
        $publicId = $olt->json('data.public_id');

        $this->postJson("/api/v1/olts/{$publicId}/ont-tr069-server-profiles", [
            'profile_id' => 1,
            'profile_name' => 'TR069_PROF',
            'url' => 'http://10.0.10.156:7547/cwmp',
            'username' => 'acs',
            'password' => 'secret',
        ])->assertCreated()->assertJsonPath('data.profile_id', 1)->assertJsonMissingPath('data.password');
    }

    public function test_terminal_user_creation_is_protected_and_never_returns_its_password(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.manage', 'olts.provision']);
        Sanctum::actingAs($user);
        $olt = \App\Models\Olt::create(['name' => 'User OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);

        $this->mock(OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('createTerminalUser')->once()->andReturn(['status' => 'applied', 'message' => 'Terminal user creation applied successfully.']);
        });

        $response = $this->postJson("/api/v1/olts/{$olt->public_id}/terminal-users", [
            'username' => 'testuser',
            'password' => 'TestUser#2026!',
            'profile_name' => 'root',
            'privilege_level' => 3,
            'reenter_limit' => 1,
            'appended_info' => 'Test account',
        ]);

        $response->assertCreated()->assertJsonPath('data.username', 'testuser')->assertJsonMissingPath('data.password');
        $this->assertDatabaseHas('olt_terminal_users', ['olt_id' => $olt->id, 'username' => 'testuser', 'profile_name' => 'root', 'privilege_level' => 3]);
        $this->assertNotEquals('TestUser#2026!', DB::table('olt_terminal_users')->value('password'));
    }

    public function test_terminal_user_username_is_unique_per_olt(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.manage', 'olts.provision']);
        Sanctum::actingAs($user);
        $olt = \App\Models\Olt::create(['name' => 'Duplicate User OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        \App\Models\OltTerminalUser::create(['olt_id' => $olt->id, 'username' => 'testuser', 'password' => 'stored-secret', 'profile_name' => 'root', 'privilege_level' => 3, 'reenter_limit' => 1, 'status' => 'ready']);

        $this->postJson("/api/v1/olts/{$olt->public_id}/terminal-users", [
            'username' => 'testuser', 'password' => 'TestUser#2026!', 'profile_name' => 'root', 'privilege_level' => 3,
        ])->assertUnprocessable()->assertJsonPath('message', 'This terminal username already exists for the OLT.');
    }

    public function test_terminal_user_mutations_require_olt_provision_permission(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.manage'], 'OLT viewer');
        Sanctum::actingAs($user);
        $olt = \App\Models\Olt::create(['name' => 'Protected User OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);

        $this->postJson("/api/v1/olts/{$olt->public_id}/terminal-users", [
            'username' => 'testuser', 'password' => 'TestUser#2026!', 'profile_name' => 'root', 'privilege_level' => 3,
        ])->assertForbidden();
    }

    public function test_terminal_user_can_be_updated_without_resending_password_and_deleted(): void
    {
        $user = $this->installationUser(['olts.view', 'olts.manage', 'olts.provision'], 'OLT editor');
        Sanctum::actingAs($user);
        $olt = \App\Models\Olt::create(['name' => 'Mutable User OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $record = \App\Models\OltTerminalUser::create(['olt_id' => $olt->id, 'username' => 'testuser', 'password' => 'StoredSecret#2026!', 'profile_name' => 'root', 'privilege_level' => 3, 'reenter_limit' => 1, 'status' => 'ready']);

        $this->mock(OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('replaceTerminalUser')->once()->andReturn(['status' => 'applied', 'message' => 'Terminal user update applied successfully.']);
            $mock->shouldReceive('deleteTerminalUser')->once();
        });

        $this->putJson("/api/v1/olts/{$olt->public_id}/terminal-users/{$record->id}", [
            'username' => 'testuser', 'profile_name' => 'ispadmin', 'privilege_level' => 2, 'reenter_limit' => 2,
        ])->assertOk()->assertJsonPath('data.profile_name', 'ispadmin')->assertJsonMissingPath('data.password');

        $this->deleteJson("/api/v1/olts/{$olt->public_id}/terminal-users/{$record->id}")->assertNoContent();
        $this->assertDatabaseMissing('olt_terminal_users', ['id' => $record->id]);
    }

    public function test_terminal_user_password_policy_can_be_updated(): void
    {
        $user = $this->installationUser(['olts.manage', 'olts.provision'], 'OLT editor');
        Sanctum::actingAs($user);
        $olt = \App\Models\Olt::create(['name' => 'Policy OLT', 'vendor' => 'huawei', 'preferred_transport' => 'ssh', 'status' => 'active']);
        $this->mock(OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('updateTerminalUserPolicy')->once()->andReturn(['status' => 'applied', 'message' => 'Terminal user password policy applied successfully.']);
        });

        $this->patchJson("/api/v1/olts/{$olt->public_id}/terminal-user-policy", ['security_enabled' => true, 'security_length' => 12])
            ->assertOk()->assertJsonPath('data.security_enabled', true)->assertJsonPath('data.security_length', 12);
    }
}
