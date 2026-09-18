<?php

namespace App\Services;

class HuaweiOltDriver implements OltDriver
{
    public function createVlanCommands(array $vlan): array
    {
        return [sprintf('vlan batch %d', $vlan['vlan_id'])];
    }

    public function createQinqCommands(array $qinq): array
    {
        // Huawei MA-series syntax: map the customer VLAN into the service VLAN.
        return [sprintf('vlan %d', $qinq['outer_vlan']), sprintf('qinq vlan-translation enable'), sprintf('port vlan-stacking vlan %d inner-vlan %d', $qinq['outer_vlan'], $qinq['inner_vlan'])];
    }

    public function provisioningPayload(string $type, array $values): array
    {
        return match ($type) {
            's_vlan' => ['operation' => 'create_s_vlan', 'values' => ['vlan_id' => $values['outer_vlan'], 'port' => $values['port'] ?? '0/3 1', 'frame' => $values['frame'], 'slot' => $values['slot'], 'port_number' => $values['port_number'], 'service_port_id' => $values['service_port_id']]],
            'c_vlan' => ['operation' => 'create_c_vlan', 'values' => ['vlan_id' => $values['inner_vlan']]],
            'ont_line_profile' => ['operation' => 'create_ont_line_profile', 'values' => ['profile_id' => $values['profile_id'], 'profile_name' => $values['ont_line_profile'], 'dba_profile_id' => $values['dba_profile_id'], 'internet_vlan' => $values['outer_vlan'], 'tr069_vlan' => $values['inner_vlan']]],
            default => throw new \InvalidArgumentException('Unsupported Huawei OLT provisioning type.'),
        };
    }

    public function provisioningDeletePayload(string $type, array $values): array
    {
        return match ($type) {
            's_vlan' => ['operation' => 'delete_s_vlan', 'values' => ['vlan_id' => $values['outer_vlan'], 'port' => $values['port'] ?? '0/3 1', 'frame' => $values['frame'], 'slot' => $values['slot'], 'port_number' => $values['port_number'], 'service_port_id' => $values['service_port_id']]],
            'c_vlan' => ['operation' => 'delete_c_vlan', 'values' => ['vlan_id' => $values['inner_vlan']]],
            'ont_line_profile' => ['operation' => 'delete_ont_line_profile', 'values' => ['profile_id' => $values['profile_id']]],
            default => throw new \InvalidArgumentException('Unsupported Huawei OLT provisioning type.'),
        };
    }


    public function deviceType(): string
    {
        return 'huawei_olt_ssh';
    }
}
