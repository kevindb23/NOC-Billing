<?php

namespace App\Services;

use RuntimeException;

class OltConnectionTester
{
    public function __construct(private RouterCommandExecutor $netmiko)
    {
    }

    /** @param array{management_endpoint:string,preferred_transport:string} $config */
    public function test(array $config): array
    {
        if ($config['preferred_transport'] === 'ssh') {
            $deviceType = ['huawei' => 'huawei_olt_ssh', 'zte' => 'zte_zxros_ssh'][$config['vendor'] ?? ''] ?? null;
            if ($deviceType === null) {
                throw new RuntimeException('The selected OLT vendor is not supported for Netmiko SSH.');
            }

            return $this->netmiko->executeNetmiko($config, $deviceType);
        }

        [$host, $port] = $this->endpoint($config['management_endpoint'], $config['preferred_transport']);
        $started = microtime(true);
        $errno = 0;
        $error = '';
        $socket = @fsockopen($host, $port, $errno, $error, 3);

        if (! is_resource($socket)) {
            throw new RuntimeException($error !== '' ? $error : "Unable to reach {$host}:{$port}.");
        }

        fclose($socket);

        return [
            'message' => "The {$config['preferred_transport']} endpoint is reachable.",
            'host' => $host,
            'port' => $port,
            'transport' => $config['preferred_transport'],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /** @return array{0:string,1:int} */
    private function endpoint(string $endpoint, string $transport): array
    {
        $endpoint = trim($endpoint);
        $defaultPort = ['ssh' => 22, 'telnet' => 23, 'api' => 80, 'netconf' => 830][$transport];
        $value = str_contains($endpoint, '://') ? $endpoint : "tcp://{$endpoint}";
        $parsed = parse_url($value);

        if (! is_array($parsed) || empty($parsed['host']) || ! preg_match('/^[A-Za-z0-9._:-]+$/', $parsed['host'])) {
            throw new RuntimeException('Enter a valid OLT management endpoint.');
        }

        $port = (int) ($parsed['port'] ?? $defaultPort);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Enter a valid OLT management port.');
        }

        return [$parsed['host'], $port];
    }
}
