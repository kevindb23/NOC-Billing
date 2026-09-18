<?php

namespace App\Services;

use App\Models\Olt;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OltProvisioningService
{
    public function __construct(private RouterCommandExecutor $executor, private OltSessionService $sessions)
    {
    }

    public function createVlan(Olt $olt, array $vlan): array
    {
        if (($vlan['service_mode'] ?? 'internet') === 'tr069') {
            return $this->apply($olt, 'create_tr069_vlan', ['vlan_id' => $vlan['vlan_id'], 'port' => $vlan['port'], 'frame' => $vlan['frame'], 'slot' => $vlan['slot'], 'port_number' => $vlan['port_number']], 'TR-069 VLAN creation');
        }
        return $this->apply($olt, 'create_c_vlan', ['vlan_id' => $vlan['vlan_id']], 'VLAN creation');
    }

    public function deleteVlan(Olt $olt, array $vlan): void
    {
        if (($vlan['service_mode'] ?? 'internet') === 'tr069' && ($vlan['frame'] === null || $vlan['slot'] === null || $vlan['port_number'] === null)) {
            throw new RuntimeException('This TR-069 VLAN record is missing its physical port details. Edit the record and save those details before deleting it.');
        }
        if (blank($olt->ssh_username) || blank($olt->ssh_password)) return;
        $operation = ($vlan['service_mode'] ?? 'internet') === 'tr069' ? 'delete_tr069_vlan' : 'delete_c_vlan';
        $values = ['vlan_id' => $vlan['vlan_id']];
        if ($operation === 'delete_tr069_vlan') $values += ['port' => $vlan['port'], 'frame' => $vlan['frame'], 'slot' => $vlan['slot'], 'port_number' => $vlan['port_number']];
        $this->sessions->provision($olt, $operation, $values);
    }

    public function createQinq(Olt $olt, array $qinq): array
    {
        $driver = OltDriverRegistry::driver($olt->vendor);
        $payload = $driver->provisioningPayload((string) ($qinq['qinq_type'] ?? 's_vlan'), $qinq);
        return $this->apply($olt, $payload['operation'], $payload['values'], 'QinQ creation');
    }

    public function deleteQinq(Olt $olt, array $qinq): void
    {
        Log::info('OLT provisioning deletion started', ['olt_id' => $olt->public_id, 'qinq' => $qinq]);
        if (($qinq['qinq_type'] ?? 's_vlan') === 's_vlan' && (blank($qinq['service_port_id']) || $qinq['frame'] === null || $qinq['slot'] === null || $qinq['port_number'] === null)) {
            throw new RuntimeException('This S-VLAN record is missing its service-port and physical port details. Edit the record and save those details before deleting it.');
        }
        if (blank($olt->ssh_username) || blank($olt->ssh_password)) return;
        $driver = OltDriverRegistry::driver($olt->vendor); $payload = $driver->provisioningDeletePayload((string) ($qinq['qinq_type'] ?? 's_vlan'), $qinq);
        $result = $this->sessions->provision($olt, $payload['operation'], $payload['values']);
        Log::info('OLT provisioning deletion acknowledged', ['olt_id' => $olt->public_id, 'operation' => $payload['operation'], 'result' => $result]);
    }

    public function createDbaProfile(Olt $olt, array $profile): array
    {
        return $this->apply($olt, 'create_dba_profile', ['profile_id' => $profile['profile_id'], 'profile_name' => $profile['profile_name'], 'bandwidth_mbps' => $profile['bandwidth_mbps']], 'DBA profile creation');
    }

    public function deleteDbaProfile(Olt $olt, array $profile): void
    {
        if (blank($olt->ssh_username) || blank($olt->ssh_password)) return;
        $this->sessions->provision($olt, 'delete_dba_profile', ['profile_id' => $profile['profile_id']]);
    }

    /** @param list<string> $commands */
    private function apply(Olt $olt, string $driverOperation, array $values, string $operation = 'Provisioning'): array
    {
        if (strtolower((string) $olt->preferred_transport) !== 'ssh') {
            throw new RuntimeException('OLT provisioning currently supports SSH only.');
        }
        if (blank($olt->ssh_username) || blank($olt->ssh_password)) {
            return ['applied' => false, 'status' => 'ready', 'values' => $values, 'message' => "{$operation} prepared; connect the OLT session to apply it."];
        }

        $result = $this->sessions->provision($olt, $driverOperation, $values);

        return ['applied' => true, 'status' => 'applied', 'message' => "{$operation} applied successfully.", 'output' => $result['output'] ?? null];
    }
}
