<?php

namespace Tests\Feature;

use App\Models\Bng;
use App\Models\BngVlanInterface;
use App\Models\Olt;
use App\Services\BngSessionService;
use App\Services\BngVlanSyncService;
use App\Services\OltProvisioningService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class BngVlanSyncApiTest extends TestCase
{
    use CreatesSingleInstallationContext;
    use RefreshDatabase;

    public function test_vlan_sync_rules_are_unique_per_bng_olt_and_mode(): void
    {
        [$bng, $olt] = $this->fixtures();
        $api = $this->actingAs(User::factory()->create(), 'sanctum');
        $payload = ['olt_id' => $olt->public_id, 'vlan_mode' => 'qinq', 'enabled' => true];

        $api->postJson("/api/v1/bngs/{$bng->public_id}/vlan-syncs", $payload)->assertCreated();
        $api->postJson("/api/v1/bngs/{$bng->public_id}/vlan-syncs", $payload)->assertUnprocessable()->assertJsonValidationErrors('vlan_mode');
    }

    public function test_disabled_vlan_sync_rule_is_returned_with_its_selected_olt(): void
    {
        [$bng, $olt] = $this->fixtures();
        $api = $this->actingAs(User::factory()->create(), 'sanctum');

        $api->postJson("/api/v1/bngs/{$bng->public_id}/vlan-syncs", [
            'olt_id' => $olt->public_id,
            'vlan_mode' => 'normal',
            'enabled' => false,
        ])->assertCreated()->assertJsonPath('data.enabled', false)->assertJsonPath('data.olt.public_id', $olt->public_id);

        $api->getJson("/api/v1/bngs/{$bng->public_id}/vlan-syncs")
            ->assertOk()
            ->assertJsonPath('data.0.vlan_mode', 'normal')
            ->assertJsonPath('data.0.enabled', false);
    }

    public function test_enabling_a_rule_reconciles_existing_olt_vlans(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $olt->vlanProvisions()->create(['vlan_id' => 3001, 'vlan_type' => 'smart', 'name' => 'Customer VLAN', 'service_mode' => 'internet', 'status' => 'applied']);
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->zeroOrMoreTimes()->andReturn(['interfaces' => [], 'added' => []]);
            $mock->shouldReceive('ensureVlanInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces[0]['name'] === 'ens17.3001';
            })->andReturn(['interfaces' => ['ens17.3001']]);
        });

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/v1/bngs/{$bng->public_id}/vlan-syncs", ['olt_id' => $olt->public_id, 'vlan_mode' => 'normal', 'enabled' => true])
            ->assertCreated()
            ->assertJsonPath('operation.warnings', []);

        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.3001', 'status' => 'applied']);
    }

    public function test_enabling_an_existing_disabled_rule_reconciles_existing_olt_vlans(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $olt->vlanProvisions()->create(['vlan_id' => 3001, 'vlan_type' => 'smart', 'name' => 'Customer VLAN', 'service_mode' => 'internet', 'status' => 'applied']);
        $rule = $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'normal', 'enabled' => false]);
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->zeroOrMoreTimes()->andReturn(['interfaces' => [], 'added' => []]);
            $mock->shouldReceive('ensureVlanInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces[0]['name'] === 'ens17.3001';
            })->andReturn(['interfaces' => ['ens17.3001']]);
        });

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson("/api/v1/bngs/{$bng->public_id}/vlan-syncs/{$rule->public_id}", ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('operation.warnings', []);

        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.3001', 'status' => 'applied']);
    }

    public function test_saving_bng_interfaces_only_updates_the_record(): void
    {
        [$bng, $olt] = $this->fixtures();
        $olt->vlanProvisions()->create(['vlan_id' => 3001, 'vlan_type' => 'smart', 'name' => 'Customer VLAN', 'service_mode' => 'internet', 'status' => 'applied']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'normal', 'enabled' => true]);
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldNotReceive('ensureVlanInterfaces');
            $mock->shouldNotReceive('removeVlanInterfaces');
        });

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson("/api/v1/bngs/{$bng->public_id}/interfaces", ['parent_interface' => 'ens17', 'egress_interface' => 'ens16'])
            ->assertOk()
            ->assertJsonPath('data.parent_interface', 'ens17')
            ->assertJsonPath('data.egress_interface', 'ens16')
            ->assertJsonMissingPath('operation');

        $this->assertDatabaseHas('bngs', ['id' => $bng->id, 'parent_interface' => 'ens17', 'egress_interface' => 'ens16']);
        $this->assertDatabaseMissing('bng_vlan_interfaces', ['interface_name' => 'ens17.3001']);
    }

    public function test_vlan_sync_rejects_an_olt_that_does_not_exist(): void
    {
        [$bng] = $this->fixtures();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/v1/bngs/{$bng->public_id}/vlan-syncs", [
                'olt_id' => '01J00000000000000000000000',
                'vlan_mode' => 'normal',
                'enabled' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('olt_id');
    }

    public function test_enabled_normal_vlan_sync_creates_the_parent_vlan_interface(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'normal', 'enabled' => true]);
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces === ['ens17.3001'];
            })->andReturn(['interfaces' => ['ens17.3001'], 'added' => ['ens17.3001']]);
            $mock->shouldReceive('ensureVlanInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces[0]['name'] === 'ens17.3001' && $interfaces[0]['vlan_id'] === 3001;
            })->andReturn(['interfaces' => ['ens17.3001']]);
        });

        $result = app(BngVlanSyncService::class)->syncNormalVlan($olt, ['id' => 1, 'vlan_id' => 3001]);

        $this->assertSame([], $result['warnings']);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['bng_id' => $bng->id, 'olt_id' => $olt->id, 'interface_name' => 'ens17.3001', 'status' => 'applied', 'outer_vlan' => 3001]);
    }

    public function test_disabled_qinq_sync_does_not_create_an_interface(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'qinq', 'enabled' => false]);

        $result = app(BngVlanSyncService::class)->syncQinq($olt, ['id' => 1, 'qinq_type' => 'c_vlan', 'outer_vlan' => 50, 'inner_vlan' => 3001]);

        $this->assertSame([], $result['warnings']);
        $this->assertSame(0, BngVlanInterface::count());
    }

    public function test_enabled_qinq_sync_creates_the_outer_and_nested_interfaces(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'qinq', 'enabled' => true]);
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces === ['ens17.50.3001'];
            })->andReturn(['interfaces' => ['ens17.50.3001'], 'added' => ['ens17.50.3001']]);
            $mock->shouldReceive('ensureVlanInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return array_column($interfaces, 'name') === ['ens17.50', 'ens17.50.3001']
                    && $interfaces[1]['parent'] === 'ens17.50';
            })->andReturn(['interfaces' => ['ens17.50', 'ens17.50.3001']]);
        });

        $result = app(BngVlanSyncService::class)->syncQinq($olt, ['id' => 10, 'qinq_type' => 'c_vlan', 'outer_vlan' => 50, 'inner_vlan' => 3001]);

        $this->assertSame([], $result['warnings']);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.50', 'outer_vlan' => 50, 'inner_vlan' => null, 'status' => 'applied']);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.50.3001', 'outer_vlan' => 50, 'inner_vlan' => 3001, 'status' => 'applied']);
    }

    public function test_enabled_qinq_sync_also_creates_the_tr069_vlan_interface(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'qinq', 'enabled' => true]);
        $olt->qinqProvisions()->create(['qinq_type' => 'c_vlan', 'outer_vlan' => 100, 'inner_vlan' => 2000, 'name' => 'Customer 2000', 'status' => 'applied']);
        $olt->vlanProvisions()->create(['vlan_id' => 25, 'vlan_type' => 'smart', 'name' => 'TR-069', 'service_mode' => 'tr069', 'status' => 'applied']);
        $captured = [];
        $this->mock(BngSessionService::class, function ($mock) use (&$captured): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces === ['ens17.100.2000'];
            })->andReturn(['interfaces' => ['ens17.100.2000'], 'added' => ['ens17.100.2000']]);
            $mock->shouldReceive('ensureVlanInterfaces')->twice()->withArgs(function (Bng $actual, array $interfaces) use (&$captured): bool {
                $captured[] = array_column($interfaces, 'name');
                return $actual->parent_interface === 'ens17';
            })->andReturn(['interfaces' => ['ens17.100', 'ens17.100.2000']]);
        });

        $result = app(BngVlanSyncService::class)->reconcileRule($bng->vlanSyncs()->first()->load('olt'));

        $this->assertSame([], $result['warnings']);
        $this->assertSame([['ens17.100', 'ens17.100.2000'], ['ens17.25']], $captured);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.25', 'outer_vlan' => 25, 'inner_vlan' => null, 'status' => 'applied']);
    }

    public function test_qinq_c_vlan_without_outer_vlan_uses_the_only_s_vlan(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $olt->qinqProvisions()->create(['qinq_type' => 's_vlan', 'outer_vlan' => 100, 'name' => 'WAN', 'status' => 'applied']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'qinq', 'enabled' => true]);
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces === ['ens17.100.2000'];
            })->andReturn(['interfaces' => ['ens17.100.2000'], 'added' => ['ens17.100.2000']]);
            $mock->shouldReceive('ensureVlanInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return array_column($interfaces, 'name') === ['ens17.100', 'ens17.100.2000'];
            })->andReturn(['interfaces' => ['ens17.100', 'ens17.100.2000']]);
        });

        $result = app(BngVlanSyncService::class)->syncQinq($olt, ['id' => 14, 'qinq_type' => 'c_vlan', 'outer_vlan' => null, 'inner_vlan' => 2000]);

        $this->assertSame([], $result['warnings']);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.100.2000', 'outer_vlan' => 100, 'inner_vlan' => 2000, 'status' => 'applied']);
    }

    public function test_qinq_child_cleanup_preserves_an_outer_vlan_used_by_another_child(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'qinq', 'enabled' => true]);
        $first = $olt->qinqProvisions()->create(['qinq_type' => 'c_vlan', 'outer_vlan' => 50, 'inner_vlan' => 3001, 'name' => 'C-VLAN 3001', 'status' => 'applied']);
        $olt->qinqProvisions()->create(['qinq_type' => 'c_vlan', 'outer_vlan' => 50, 'inner_vlan' => 3002, 'name' => 'C-VLAN 3002', 'status' => 'applied']);
        BngVlanInterface::insert([
            ['bng_id' => $bng->id, 'olt_id' => $olt->id, 'vlan_mode' => 'qinq', 'outer_vlan' => 50, 'inner_vlan' => null, 'interface_name' => 'ens17.50', 'status' => 'applied', 'created_at' => now(), 'updated_at' => now()],
            ['bng_id' => $bng->id, 'olt_id' => $olt->id, 'vlan_mode' => 'qinq', 'outer_vlan' => 50, 'inner_vlan' => 3001, 'interface_name' => 'ens17.50.3001', 'status' => 'applied', 'created_at' => now(), 'updated_at' => now()],
            ['bng_id' => $bng->id, 'olt_id' => $olt->id, 'vlan_mode' => 'qinq', 'outer_vlan' => 50, 'inner_vlan' => 3002, 'interface_name' => 'ens17.50.3002', 'status' => 'applied', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->zeroOrMoreTimes()->andReturn(['interfaces' => [], 'added' => []]);
            $mock->shouldReceive('removePppoeInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces === ['ens17.50.3001'];
            })->andReturn(['interfaces' => ['ens17.50.3001'], 'removed' => ['ens17.50.3001']]);
            $mock->shouldReceive('removeVlanInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces[0]['name'] === 'ens17.50.3001';
            })->andReturn(['interfaces' => ['ens17.50.3001']]);
        });

        app(BngVlanSyncService::class)->removeQinq($olt, $first->toArray());

        $this->assertDatabaseMissing('bng_vlan_interfaces', ['interface_name' => 'ens17.50.3001']);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.50']);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.50.3002']);
    }

    public function test_bng_failure_keeps_the_desired_interface_in_error_state(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'normal', 'enabled' => true]);
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->zeroOrMoreTimes()->andReturn(['interfaces' => [], 'added' => []]);
            $mock->shouldReceive('ensureVlanInterfaces')->once()->andThrow(new \RuntimeException('The BNG session service is not running.'));
        });

        $result = app(BngVlanSyncService::class)->syncNormalVlan($olt, ['id' => 1, 'vlan_id' => 3001]);

        $this->assertCount(1, $result['warnings']);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['interface_name' => 'ens17.3001', 'status' => 'error', 'last_error' => 'The BNG session service is not running.']);
    }

    public function test_olt_vlan_creation_syncs_enabled_bng_rules_after_saving_the_olt_record(): void
    {
        [$bng, $olt] = $this->fixtures();
        $bng->update(['parent_interface' => 'ens17']);
        $bng->vlanSyncs()->create(['olt_id' => $olt->id, 'vlan_mode' => 'normal', 'enabled' => true]);
        Sanctum::actingAs($this->installationUser(['olts.view', 'olts.create', 'olts.provision']));
        $api = $this;

        $this->mock(OltProvisioningService::class, function ($mock): void {
            $mock->shouldReceive('createVlan')->once()->andReturn(['status' => 'applied']);
        });
        $this->mock(BngSessionService::class, function ($mock): void {
            $mock->shouldReceive('ensurePppoeInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces === ['ens17.3001'];
            })->andReturn(['interfaces' => ['ens17.3001'], 'added' => ['ens17.3001']]);
            $mock->shouldReceive('ensureVlanInterfaces')->once()->withArgs(function (Bng $actual, array $interfaces): bool {
                return $actual->parent_interface === 'ens17' && $interfaces === [[
                    'name' => 'ens17.3001',
                    'parent' => 'ens17',
                    'vlan_id' => 3001,
                ]];
            })->andReturn(['interfaces' => ['ens17.3001']]);
        });

        $api->postJson("/api/v1/olts/{$olt->public_id}/vlans", [
            'name' => 'Customer VLAN',
            'vlan_id' => 3001,
            'vlan_type' => 'smart',
            'service_mode' => 'internet',
        ])->assertCreated()->assertJsonPath('operation.bng_sync.warnings', []);

        $this->assertDatabaseHas('olt_vlan_provisions', ['olt_id' => $olt->id, 'vlan_id' => 3001]);
        $this->assertDatabaseHas('bng_vlan_interfaces', ['bng_id' => $bng->id, 'interface_name' => 'ens17.3001', 'status' => 'applied']);
    }

    private function fixtures(): array
    {
        return [
            Bng::create(['name' => 'Linux BNG', 'vendor' => 'linux', 'management_endpoint' => '10.0.0.10', 'preferred_transport' => 'ssh', 'status' => 'active']),
            Olt::create(['name' => 'Access OLT', 'vendor' => 'huawei', 'management_endpoint' => '10.0.0.20', 'preferred_transport' => 'ssh', 'status' => 'active']),
        ];
    }
}
