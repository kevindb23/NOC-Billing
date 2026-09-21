<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\AcsServer;
use App\Models\ActivationPreset;
use App\Models\Olt;
use App\Models\OltDbaProfile;
use App\Models\OltOntServiceProfile;
use App\Models\OltOntTr069ServerProfile;
use App\Models\OltOntWanProfile;
use App\Models\OltQinqProvision;
use App\Models\OltVlanProvision;
use App\Models\Ont;
use App\Models\Plan;
use App\Models\PlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class ActivationApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSingleInstallationContext;

    public function test_activation_binds_a_subscriber_plan_ont_and_vlan_pair(): void
    {
        $user = $this->installationUser();
        [$customer, $version, $ont] = $this->fixtures();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/activations', [
            'subscriber_id' => $customer->public_id,
            'plan_version_id' => $version->id,
            'ont_public_id' => $ont->public_id,
            'olt_public_id' => $ont->olt->public_id,
            'preset_public_id' => $ont->olt->activationPresets->first()->public_id,
            'provisioning_type' => 'vlan',
            'vlan_provision_id' => $ont->olt->vlanProvisions->first()->id,
            'starts_on' => '2026-09-19',
        ])->assertCreated();

        $response->assertJsonPath('data.c_vlan', 120)->assertJsonPath('data.s_vlan', null);
        $this->assertDatabaseHas('activations', ['ont_id' => $ont->id, 'c_vlan' => 120, 's_vlan' => null, 'provisioning_type' => 'vlan']);
        $this->assertDatabaseHas('subscriptions', ['plan_version_id' => $version->id]);
        $this->assertDatabaseCount('billing_statements', 1);
    }

    public function test_activation_does_not_call_acs_provisioning(): void
    {
        $user = $this->installationUser();
        [$customer, $version, $ont] = $this->fixtures();
        $activation = $this->actingAs($user, 'sanctum')->postJson('/api/v1/activations', [
            'subscriber_id' => $customer->public_id,
            'plan_version_id' => $version->id,
            'ont_public_id' => $ont->public_id,
            'olt_public_id' => $ont->olt->public_id,
            'provisioning_type' => 'vlan',
            'vlan_provision_id' => $ont->olt->vlanProvisions->first()->id,
            'starts_on' => '2026-09-19',
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/activations/'.$activation->json('data.public_id').'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_bulk_activation_reports_each_failed_row_without_deleting_successful_rows(): void
    {
        $user = $this->installationUser();
        [$customer, $version, $ont] = $this->fixtures();
        $other = Customer::create(['customer_number' => 'CUS-000002', 'customer_type' => 'residential', 'legal_name' => 'Second Subscriber']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/activations/bulk', ['items' => [
            ['subscriber_id' => $customer->public_id, 'plan_version_id' => $version->id, 'ont_public_id' => $ont->public_id, 'olt_public_id' => $ont->olt->public_id, 'preset_public_id' => $ont->olt->activationPresets->first()->public_id, 'provisioning_type' => 'vlan', 'vlan_provision_id' => $ont->olt->vlanProvisions->first()->id, 'starts_on' => '2026-09-19'],
            ['subscriber_id' => $other->public_id, 'plan_version_id' => $version->id, 'ont_public_id' => $ont->public_id, 'olt_public_id' => $ont->olt->public_id, 'preset_public_id' => $ont->olt->activationPresets->first()->public_id, 'provisioning_type' => 'vlan', 'vlan_provision_id' => $ont->olt->vlanProvisions->first()->id, 'starts_on' => '2026-09-19'],
        ]])->assertOk();

        $response->assertJsonPath('data.created_count', 1)->assertJsonPath('data.error_count', 1);
        $this->assertDatabaseCount('activations', 1);
    }

    public function test_activation_requires_create_permission(): void
    {
        $user = $this->installationUser(['activations.view'], 'Operator');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/activations', [])->assertForbidden();
    }

    public function test_activation_options_are_scoped_to_the_selected_olt(): void
    {
        $user = $this->installationUser();
        [, , $ont] = $this->fixtures();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/activation-options?olt_public_id='.$ont->olt->public_id)
            ->assertOk()
            ->assertJsonPath('data.olt.public_id', $ont->olt->public_id)
            ->assertJsonPath('data.setup.type', 'vlan')
            ->assertJsonPath('data.setup.vlan_provisions.0.vlan_id', 120)
            ->assertJsonPath('data.presets.0.name', 'Standard home package');
    }

    public function test_activation_options_include_legacy_c_vlan_records_with_an_inner_vlan(): void
    {
        $user = $this->installationUser();
        $olt = Olt::create(['name' => 'OLT-QINQ', 'vendor' => 'Huawei']);
        OltQinqProvision::create([
            'olt_id' => $olt->id,
            'outer_vlan' => 100,
            'inner_vlan' => null,
            'qinq_type' => 's_vlan',
            'name' => 'WAN',
            'status' => 'ready',
        ]);
        $this->createCompleteQinqProfiles($olt);
        OltQinqProvision::create([
            'olt_id' => $olt->id,
            'outer_vlan' => null,
            'inner_vlan' => 3001,
            'qinq_type' => 's_vlan',
            'name' => 'Legacy C-VLAN',
            'status' => 'ready',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/activation-options?olt_public_id='.$olt->public_id)
            ->assertOk()
            ->assertJsonPath('data.setup.type', 'qinq')
            ->assertJsonPath('data.setup.qinq_provisions.0.inner_vlan', 3001)
            ->assertJsonPath('data.setup.qinq_provisions.0.outer_vlan', 100);
    }

    public function test_activation_can_be_deactivated_and_deleted_from_history(): void
    {
        $user = $this->installationUser();
        [$customer, $version, $ont] = $this->fixtures();

        $activation = $this->actingAs($user, 'sanctum')->postJson('/api/v1/activations', [
            'subscriber_id' => $customer->public_id,
            'plan_version_id' => $version->id,
            'ont_public_id' => $ont->public_id,
            'olt_public_id' => $ont->olt->public_id,
            'provisioning_type' => 'vlan',
            'vlan_provision_id' => $ont->olt->vlanProvisions->first()->id,
            'starts_on' => '2026-09-19',
        ])->assertCreated();

        $publicId = $activation->json('data.public_id');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/activations/'.$publicId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('activations', ['public_id' => $publicId, 'status' => 'inactive']);
        $this->assertDatabaseHas('subscriptions', ['id' => $activation->json('data.subscription_id'), 'status' => 'inactive']);
        $this->assertDatabaseHas('subscriber_services', ['id' => $activation->json('data.subscriber_service_id'), 'status' => 'inactive']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/activations/'.$publicId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('message', 'Activation was already inactive.');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/activations/'.$publicId)
            ->assertOk();

        $this->assertDatabaseMissing('activations', ['public_id' => $publicId]);
    }

    public function test_qinq_deactivation_removes_service_ports_before_deleting_the_ont(): void
    {
        $user = $this->installationUser();
        [$customer, $version, $ont] = $this->fixtures();
        $olt = $ont->olt;
        $cVlan = OltQinqProvision::create([
            'olt_id' => $olt->id,
            'outer_vlan' => 50,
            'inner_vlan' => 3001,
            'qinq_type' => 'c_vlan',
            'name' => 'Home 3001',
            'status' => 'ready',
        ]);
        OltQinqProvision::create([
            'olt_id' => $olt->id,
            'outer_vlan' => 50,
            'inner_vlan' => null,
            'qinq_type' => 's_vlan',
            'service_port_id' => 10001,
            'frame' => 0,
            'slot' => 2,
            'port_number' => 0,
            'name' => 'Service 50',
            'status' => 'ready',
        ]);
        $this->createCompleteQinqProfiles($olt);

        $this->mock(\App\Services\OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('activateOnt')->once()->andReturn(['applied' => true]);
            $mock->shouldReceive('deactivateOnt')->once()->withArgs(function ($olt, array $values): bool {
                return $values['frame'] === 0
                    && $values['slot'] === 1
                    && $values['pon_port'] === 2
                    && $values['ont_id'] === 0
                    && $values['service_port_ids'] === [10001, 20001];
            })->andReturn(['applied' => true]);
        });

        $activation = $this->actingAs($user, 'sanctum')->postJson('/api/v1/activations', [
            'subscriber_id' => $customer->public_id,
            'plan_version_id' => $version->id,
            'ont_public_id' => $ont->public_id,
            'olt_public_id' => $olt->public_id,
            'provisioning_type' => 'qinq',
            'qinq_provision_id' => $cVlan->id,
            'starts_on' => '2026-09-19',
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/activations/'.$activation->json('data.public_id').'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('onts', ['id' => $ont->id, 'ont_id' => null]);
    }

    public function test_qinq_activation_preview_automatically_allocates_the_first_free_ont_id(): void
    {
        $user = $this->installationUser();
        [, $version, $ont] = $this->fixtures();
        $olt = $ont->olt;
        $ont->update(['ont_id' => null]);
        $cVlan = OltQinqProvision::create(['olt_id' => $olt->id, 'outer_vlan' => null, 'inner_vlan' => 3001, 'qinq_type' => 'c_vlan', 'name' => 'Home 3001', 'status' => 'ready']);
        $sVlan = OltQinqProvision::create(['olt_id' => $olt->id, 'outer_vlan' => 50, 'inner_vlan' => null, 'qinq_type' => 's_vlan', 'service_port_id' => 10001, 'frame' => 0, 'slot' => 2, 'port_number' => 0, 'name' => 'Service 50', 'status' => 'ready']);
        $this->createCompleteQinqProfiles($olt);

        $this->mock(\App\Services\OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('previewOntActivation')->once()->withArgs(function ($olt, array $values): bool {
                return $values['ont_id'] === 0 && $values['pon_port'] === 2;
            })->andReturn(['commands' => ['config', 'ont add 2 0 sn-auth "HWTC0001" omci desc "SUB_3001_50"', 'return', 'save']]);
        });

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/activation-preview', [
            'subscriber_id' => 'customer-not-used-for-preview',
            'plan_version_id' => $version->id,
            'ont_public_id' => $ont->public_id,
            'olt_public_id' => $olt->public_id,
            'provisioning_type' => 'qinq',
            'qinq_provision_id' => $cVlan->id,
            'starts_on' => '2026-09-19',
        ])->assertOk();

        $response->assertJsonPath('data.supported', true)->assertJsonPath('data.commands.1', 'ont add 2 0 sn-auth "HWTC0001" omci desc "SUB_3001_50"');
        $this->assertDatabaseCount('activations', 0);
        $this->assertDatabaseHas('olt_qinq_provisions', ['id' => $sVlan->id, 'service_port_id' => 10001]);
    }

    public function test_qinq_preview_uses_configured_olt_profiles_without_a_preset(): void
    {
        $user = $this->installationUser();
        [, $version, $ont] = $this->fixtures();
        $olt = $ont->olt;
        $cVlan = OltQinqProvision::create(['olt_id' => $olt->id, 'outer_vlan' => 50, 'inner_vlan' => 3001, 'qinq_type' => 'c_vlan', 'name' => 'Home 3001', 'status' => 'ready']);
        OltQinqProvision::create(['olt_id' => $olt->id, 'profile_id' => 17, 'qinq_type' => 'ont_line_profile', 'name' => 'Line 17', 'status' => 'ready']);
        OltOntServiceProfile::create(['olt_id' => $olt->id, 'profile_id' => 15, 'profile_name' => 'Service 15', 'eth_port_count' => 1, 'port_modes' => ['1' => 'transparent'], 'status' => 'ready']);
        OltOntWanProfile::create(['olt_id' => $olt->id, 'profile_id' => 15, 'profile_name' => 'WAN 15', 'nat_enabled' => false, 'status' => 'ready']);
        OltOntWanProfile::create(['olt_id' => $olt->id, 'profile_id' => 16, 'profile_name' => 'WAN 16', 'nat_enabled' => false, 'status' => 'ready']);
        OltOntTr069ServerProfile::create(['olt_id' => $olt->id, 'profile_id' => 15, 'profile_name' => 'TR-069 15', 'url' => 'http://acs.test', 'username' => 'acs', 'password' => 'secret', 'status' => 'ready']);
        OltVlanProvision::create(['olt_id' => $olt->id, 'vlan_id' => 25, 'name' => 'TR-069', 'service_mode' => 'tr069', 'status' => 'ready']);
        OltQinqProvision::create(['olt_id' => $olt->id, 'outer_vlan' => 50, 'inner_vlan' => null, 'qinq_type' => 's_vlan', 'service_port_id' => 10001, 'frame' => 0, 'slot' => 2, 'port_number' => 0, 'name' => 'Service 50', 'status' => 'ready']);

        $this->mock(\App\Services\OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('previewOntActivation')->once()->withArgs(function ($olt, array $values): bool {
                return $values['line_profile_id'] === 17
                    && $values['service_profile_id'] === 15
                    && $values['tr069_profile_id'] === 15
                    && $values['wan_profile_ids'] === [15, 16]
                    && $values['tr069_vlan'] === 25;
            })->andReturn(['commands' => ['config', 'return', 'save']]);
        });

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/activation-preview', [
            'subscriber_id' => 'customer-not-used-for-preview',
            'plan_version_id' => $version->id,
            'ont_public_id' => $ont->public_id,
            'olt_public_id' => $olt->public_id,
            'provisioning_type' => 'qinq',
            'qinq_provision_id' => $cVlan->id,
            'starts_on' => '2026-09-19',
        ])->assertOk()->assertJsonPath('data.commands.0', 'config');
    }

    public function test_qinq_preview_rejects_when_all_ont_ids_on_the_pon_are_used(): void
    {
        $user = $this->installationUser();
        [, $version, $ont] = $this->fixtures();
        $olt = $ont->olt;
        $ont->update(['ont_id' => null]);
        for ($ontId = 0; $ontId < 64; $ontId++) {
            Ont::create(['olt_id' => $olt->id, 'frame' => 0, 'slot' => 1, 'pon_port' => 2, 'ont_id' => $ontId, 'serial_number' => 'USED'.str_pad((string) $ontId, 4, '0', STR_PAD_LEFT), 'name' => 'Used ONT '.$ontId, 'status' => 'discovered']);
        }
        $cVlan = OltQinqProvision::create(['olt_id' => $olt->id, 'outer_vlan' => 50, 'inner_vlan' => 3001, 'qinq_type' => 'c_vlan', 'name' => 'Home 3001', 'status' => 'ready']);
        OltQinqProvision::create(['olt_id' => $olt->id, 'outer_vlan' => 50, 'inner_vlan' => null, 'qinq_type' => 's_vlan', 'service_port_id' => 10001, 'frame' => 0, 'slot' => 2, 'port_number' => 0, 'name' => 'Service 50', 'status' => 'ready']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/activation-preview', [
            'subscriber_id' => 'customer-not-used-for-preview',
            'plan_version_id' => $version->id,
            'ont_public_id' => $ont->public_id,
            'olt_public_id' => $olt->public_id,
            'provisioning_type' => 'qinq',
            'qinq_provision_id' => $cVlan->id,
            'starts_on' => '2026-09-19',
        ])->assertStatus(422)->assertJsonPath('message', 'No ONT ID is available on PON port 2. Increase the ONT capacity setting or release an assigned ONT ID.');
    }

    /** @return array{Customer, PlanVersion, Ont} */
    private function fixtures(): array
    {
        $cycle = BillingCycle::create(['name' => 'Monthly', 'interval_unit' => 'month']);
        $plan = Plan::create(['billing_cycle_id' => $cycle->id, 'code' => 'HOME-100', 'name' => 'Home 100', 'service_type' => 'internet']);
        $version = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'recurring_price_minor' => 150000, 'setup_fee_minor' => 0, 'currency' => 'PHP', 'download_kbps' => 100000, 'upload_kbps' => 50000, 'effective_from' => '2026-01-01']);
        $customer = Customer::create(['customer_number' => 'CUS-000001', 'customer_type' => 'residential', 'legal_name' => 'First Subscriber']);
        $olt = Olt::create(['name' => 'OLT-1', 'vendor' => 'Huawei']);
        OltDbaProfile::create(['olt_id' => $olt->id, 'profile_id' => 1, 'profile_name' => 'Default DBA', 'bandwidth_mbps' => 100, 'status' => 'ready']);
        $vlan = OltVlanProvision::create(['olt_id' => $olt->id, 'vlan_id' => 120, 'name' => 'Internet VLAN', 'service_mode' => 'internet', 'status' => 'ready']);
        ActivationPreset::create(['olt_id' => $olt->id, 'name' => 'Standard home package']);
        $ont = Ont::create(['olt_id' => $olt->id, 'frame' => 0, 'slot' => 1, 'pon_port' => 2, 'ont_id' => 0, 'serial_number' => 'HWTC0001', 'name' => 'ONT-1', 'status' => 'discovered']);

        return [$customer, $version, $ont];
    }

    private function createCompleteQinqProfiles(Olt $olt): void
    {
        OltQinqProvision::create(['olt_id' => $olt->id, 'profile_id' => 17, 'qinq_type' => 'ont_line_profile', 'name' => 'Line 17', 'status' => 'ready']);
        OltOntServiceProfile::create(['olt_id' => $olt->id, 'profile_id' => 15, 'profile_name' => 'Service 15', 'eth_port_count' => 1, 'port_modes' => ['1' => 'transparent'], 'status' => 'ready']);
        OltOntWanProfile::create(['olt_id' => $olt->id, 'profile_id' => 15, 'profile_name' => 'WAN 15', 'nat_enabled' => false, 'status' => 'ready']);
        OltOntTr069ServerProfile::create(['olt_id' => $olt->id, 'profile_id' => 15, 'profile_name' => 'TR-069 15', 'url' => 'http://acs.test', 'username' => 'acs', 'password' => 'secret', 'status' => 'ready']);
    }
}
