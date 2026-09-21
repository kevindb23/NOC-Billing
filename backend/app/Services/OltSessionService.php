<?php

namespace App\Services;

use App\Models\Olt;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OltSessionService
{
    public function start(Olt $olt): array { return $this->send($this->payload($olt, 'start')); }
    public function stop(Olt $olt): array { return $this->send(['action' => 'stop', 'olt_id' => $olt->public_id]); }
    public function status(Olt $olt): array { return $this->send(['action' => 'status', 'olt_id' => $olt->public_id]); }
    public function discover(Olt $olt): array { return $this->send(['action' => 'discover', 'olt_id' => $olt->public_id, 'device_type' => OltDriverRegistry::driver($olt->vendor)->deviceType(), 'operation' => 'discover_onts']); }
    public function provision(Olt $olt, string $operation, array $values): array { $safeValues = $values; if (array_key_exists('password', $safeValues)) $safeValues['password'] = '[redacted]'; Log::info('OLT session operation requested', ['olt_id' => $olt->public_id, 'operation' => $operation, 'values' => $safeValues]); $result = $this->send(['action' => 'provision', 'olt_id' => $olt->public_id, 'device_type' => OltDriverRegistry::driver($olt->vendor)->deviceType(), 'operation' => $operation, 'values' => $values]); Log::info('OLT session operation completed', ['olt_id' => $olt->public_id, 'operation' => $operation, 'result' => $result]); return $result; }
    public function previewProvision(Olt $olt, string $operation, array $values): array { return app(RouterCommandExecutor::class)->previewNetmikoProvisioning(OltDriverRegistry::driver($olt->vendor)->deviceType(), $operation, $values); }

    private function payload(Olt $olt, string $action): array
    {
        if ($olt->preferred_transport !== 'ssh') throw new RuntimeException('Persistent sessions currently support SSH only.');
        if (blank($olt->ssh_username) || blank($olt->ssh_password)) throw new RuntimeException('Save SSH credentials on the OLT before connecting.');
        [$host, $port] = $this->endpoint($olt->management_endpoint);
        return ['action' => $action, 'olt_id' => $olt->public_id, 'device_type' => OltDriverRegistry::driver($olt->vendor)->deviceType(), 'host' => $host, 'port' => $port, 'username' => $olt->ssh_username, 'password' => $olt->ssh_password];
    }

    private function send(array $payload): array
    {
        $socket = (string) config('router.olt_session_socket', '/run/olt-session-service.sock');
        $connection = @stream_socket_client("unix://{$socket}", $errno, $error, 2);
        if (! $connection) { Log::error('OLT session service socket unavailable', ['socket' => $socket]); throw new RuntimeException('The OLT session service is not running. Start olt-session.service.'); }
        fwrite($connection, json_encode($payload, JSON_THROW_ON_ERROR));
        stream_set_timeout($connection, 90); $response = json_decode((string) fgets($connection), true); fclose($connection);
        if (! is_array($response) || ! ($response['ok'] ?? false)) { Log::error('OLT session service returned failure', ['response' => $response]); throw new RuntimeException((string) ($response['message'] ?? 'The OLT session service failed.')); }
        return $response;
    }

    private function endpoint(?string $endpoint): array
    {
        $value = str_contains((string) $endpoint, '://') ? $endpoint : "tcp://{$endpoint}"; $parsed = parse_url($value);
        if (! is_array($parsed) || empty($parsed['host'])) throw new RuntimeException('Enter a valid OLT management endpoint.');
        return [$parsed['host'], (int) ($parsed['port'] ?? 22)];
    }
}
