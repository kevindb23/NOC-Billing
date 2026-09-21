<?php

namespace App\Services;

use App\Models\Bng;
use RuntimeException;

class BngSessionService
{
    public function start(Bng $bng): array
    {
        return $this->send($this->payload($bng, 'start'));
    }

    public function stop(Bng $bng): array
    {
        return $this->send(['action' => 'stop', 'entity' => 'bng', 'entity_id' => $bng->public_id]);
    }

    public function status(Bng $bng): array
    {
        return $this->send(['action' => 'status', 'entity' => 'bng', 'entity_id' => $bng->public_id]);
    }

    public function readAccelPppConfig(Bng $bng): array
    {
        return $this->provision($bng, 'read_accel_ppp_config');
    }

    public function previewAccelPppConfig(Bng $bng, array $values): array
    {
        return $this->provision($bng, 'preview_accel_ppp_config', $values);
    }

    public function saveAccelPppConfig(Bng $bng, array $values): array
    {
        return $this->provision($bng, 'save_accel_ppp_config', $values);
    }

    public function previewIptables(Bng $bng, array $values): array
    {
        return $this->provision($bng, 'preview_iptables', $values);
    }

    public function saveIptables(Bng $bng, array $values): array
    {
        return $this->provision($bng, 'save_iptables', $values);
    }

    public function ensureVlanInterfaces(Bng $bng, array $interfaces): array
    {
        return $this->provision($bng, 'ensure_vlan_interfaces', ['parent_interface' => $bng->parent_interface, 'interfaces' => $interfaces]);
    }

    public function ensurePppoeInterfaces(Bng $bng, array $interfaces): array
    {
        return $this->provision($bng, 'ensure_pppoe_interfaces', ['interfaces' => array_values(array_unique($interfaces))]);
    }

    public function removePppoeInterfaces(Bng $bng, array $interfaces): array
    {
        return $this->provision($bng, 'remove_pppoe_interfaces', ['interfaces' => array_values(array_unique($interfaces))]);
    }

    public function removeVlanInterfaces(Bng $bng, array $interfaces): array
    {
        return $this->provision($bng, 'remove_vlan_interfaces', ['parent_interface' => $bng->parent_interface, 'interfaces' => $interfaces]);
    }

    private function provision(Bng $bng, string $operation, array $values = []): array
    {
        $capability = str_contains($operation, 'vlan_interfaces') ? 'vlan_sync' : 'accel_ppp';
        if (!BngVendorRegistry::driver($bng->vendor)[$capability]) {
            throw new RuntimeException($capability === 'vlan_sync' ? 'VLAN interface synchronization is supported only by the Linux BNG driver.' : 'Accel-PPP configuration is supported only by the Linux BNG driver.');
        }

        $payload = $this->payload($bng, 'provision');
        $payload['operation'] = $operation;
        $payload['values'] = $values;
        $response = $this->send($payload);
        return is_array($response['output'] ?? null) ? $response['output'] : $response;
    }

    private function payload(Bng $bng, string $action): array
    {
        if ($bng->preferred_transport !== 'ssh') {
            throw new RuntimeException('Persistent BNG sessions support SSH only.');
        }
        if (blank($bng->ssh_username) || blank($bng->ssh_password)) {
            throw new RuntimeException('Save SSH credentials on the BNG before connecting.');
        }

        $endpoint = str_contains((string) $bng->management_endpoint, '://')
            ? $bng->management_endpoint
            : 'tcp://' . $bng->management_endpoint;
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['host'])) {
            throw new RuntimeException('Enter a valid BNG management endpoint.');
        }

        return [
            'action' => $action,
            'entity' => 'bng',
            'entity_id' => $bng->public_id,
            'device_type' => BngVendorRegistry::driver($bng->vendor)['netmiko_device_type'],
            'host' => $parts['host'],
            'port' => (int) ($parts['port'] ?? 22),
            'username' => $bng->ssh_username,
            'password' => $bng->ssh_password,
        ];
    }

    private function send(array $payload): array
    {
        $socket = (string) config('router.bng_session_socket');
        $connection = @stream_socket_client("unix://{$socket}", $errno, $error, 2);
        if (!$connection) {
            throw new RuntimeException('The BNG session service is not running.');
        }

        fwrite($connection, json_encode($payload, JSON_THROW_ON_ERROR));
        stream_set_timeout($connection, 30);
        $response = json_decode((string) fgets($connection), true);
        fclose($connection);
        if (!is_array($response) || !($response['ok'] ?? false)) {
            throw new RuntimeException((string) ($response['message'] ?? 'The BNG session service failed.'));
        }

        return $response;
    }
}
