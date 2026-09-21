<?php

namespace App\Services;

class HsgqOltDriver implements OltDriver
{
    public function createVlanCommands(array $vlan): array
    {
        $range = ! empty($vlan['vlan_to']) ? sprintf(' to %d', $vlan['vlan_to']) : '';
        $type = ($vlan['vlan_type'] ?? 'smart') === 'to' ? '' : ' '.($vlan['vlan_type'] ?? 'smart');
        return [sprintf('vlan %d%s%s', $vlan['vlan_id'], $range, $type)];
    }

    public function createQinqCommands(array $qinq): array
    {
        return [sprintf('vlan %d', $qinq['outer_vlan']), 'qinq vlan-translation enable', sprintf('port vlan-stacking vlan %d inner-vlan %d', $qinq['outer_vlan'], $qinq['inner_vlan'])];
    }

    public function provisioningPayload(string $type, array $values): array
    {
        $profileName = $values['profile_name'] ?? $values['ont_line_profile'] ?? '';

        return match ($type) {
            's_vlan' => ['operation' => 'create_s_vlan', 'values' => ['vlan_id' => $values['outer_vlan'], 'port' => $values['port'] ?? '0/3 1', 'frame' => $values['frame'], 'slot' => $values['slot'], 'port_number' => $values['port_number'], 'service_port_id' => $values['service_port_id']]],
            'c_vlan' => ['operation' => 'create_c_vlan', 'values' => ['vlan_id' => $values['inner_vlan']]],
            'ont_line_profile' => ['operation' => 'create_ont_line_profile', 'values' => ['profile_id' => $values['profile_id'], 'profile_name' => $profileName, 'dba_profile_id' => $values['dba_profile_id'], 'internet_vlan' => $values['outer_vlan'], 'tr069_vlan' => $values['inner_vlan'], 'tr069_management_enabled' => $values['tr069_management_enabled'] ?? true, 'tr069_ip_index' => $values['tr069_ip_index'] ?? 1, 'omcc_encrypt_enabled' => $values['omcc_encrypt_enabled'] ?? true]],
            default => throw new \InvalidArgumentException('Unsupported HSGQ OLT provisioning type.'),
        };
    }

    public function provisioningDeletePayload(string $type, array $values): array
    {
        return match ($type) {
            's_vlan' => ['operation' => 'delete_s_vlan', 'values' => ['vlan_id' => $values['outer_vlan'], 'port' => $values['port'] ?? '0/3 1', 'frame' => $values['frame'], 'slot' => $values['slot'], 'port_number' => $values['port_number'], 'service_port_id' => $values['service_port_id']]],
            'c_vlan' => ['operation' => 'delete_c_vlan', 'values' => ['vlan_id' => $values['inner_vlan']]],
            'ont_line_profile' => ['operation' => 'delete_ont_line_profile', 'values' => ['profile_id' => $values['profile_id']]],
            default => throw new \InvalidArgumentException('Unsupported HSGQ OLT provisioning type.'),
        };
    }

    public function ontServiceProfilePayload(array $values): array
    {
        return ['operation' => 'create_ont_service_profile', 'values' => $values];
    }

    public function deviceType(): string
    {
        return 'hsgq_olt_ssh';
    }
}
