<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

class RouterCommandExecutor
{
    /**
     * Execute a vendor-neutral router operation through the Netmiko bridge.
     * Vendor-specific differences are limited to the registry driver and syntax.
     *
     * @param  array{vendor:string,preferred_transport:string,management_endpoint:string,username:string,password:string}  $config
     * @return array<string, mixed>
     */
    public function execute(array $config, string $operation = 'test_connection', ?string $command = null): array
    {
        $transport = strtolower((string) ($config['preferred_transport'] ?? ''));
        if ($transport !== 'ssh') {
            throw new InvalidArgumentException('The selected router transport is not supported.');
        }

        $driver = RouterVendorRegistry::driver((string) $config['vendor']);
        [$host, $port] = $this->endpoint((string) $config['management_endpoint']);
        $command ??= RouterVendorRegistry::command((string) $config['vendor'], $operation);

        $result = $this->runBridge([
            'operation' => $operation,
            'command' => $command,
            'device' => [
                'device_type' => $driver['netmiko_device_type'],
                'host' => $host,
                'port' => $port,
                'username' => $config['username'],
                'password' => $config['password'],
                'timeout' => 8,
            ],
        ]);

        return [
            'message' => $result['message'] ?? 'Router operation completed.',
            'operation' => $operation,
            'transport' => $transport,
            'vendor' => $config['vendor'],
            'driver' => $driver['netmiko_device_type'],
            'host' => $host,
            'port' => $port,
            'output' => $result['output'] ?? null,
        ];
    }

    /** Execute the shared Netmiko SSH path for any supported network device. */
    public function executeNetmiko(array $config, string $deviceType): array
    {
        [$host, $port] = $this->endpoint((string) $config['management_endpoint']);
        return $this->runBridge([
            'operation' => 'test_connection',
            'device' => [
                'device_type' => $deviceType,
                'host' => $host,
                'port' => $port,
                'username' => $config['username'],
                'password' => $config['password'],
                'timeout' => 8,
            ],
        ]);
    }

    /** Execute vendor-specific configuration commands through the shared Netmiko bridge. */
    public function executeNetmikoConfig(array $config, string $deviceType, array $commands): array
    {
        [$host, $port] = $this->endpoint((string) $config['management_endpoint']);
        return $this->runBridge([
            'operation' => 'config',
            'commands' => array_values($commands),
            'device' => ['device_type' => $deviceType, 'host' => $host, 'port' => $port, 'username' => $config['username'], 'password' => $config['password'], 'timeout' => 15],
        ]);
    }

    /** Execute a vendor driver operation through the persistent-capable Python bridge. */
    public function executeNetmikoProvisioning(array $config, string $deviceType, string $operation, array $values): array
    {
        [$host, $port] = $this->endpoint((string) $config['management_endpoint']);
        return $this->runBridge(['operation' => 'provision', 'provisioning' => $operation, 'values' => $values, 'device' => ['device_type' => $deviceType, 'host' => $host, 'port' => $port, 'username' => $config['username'], 'password' => $config['password'], 'timeout' => 20]]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runBridge(array $payload): array
    {
        $python = (string) config('router.netmiko_python', base_path('.venv/bin/python'));
        $script = (string) config('router.netmiko_script', base_path('scripts/netmiko_bridge.py'));
        if (! is_executable($python)) {
            throw new RuntimeException('Netmiko is not installed on the application server.');
        }
        if (! is_file($script)) {
            throw new RuntimeException('The Netmiko bridge is not available on the application server.');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open([$python, $script], $descriptors, $pipes, base_path());
        if (! is_resource($process)) {
            throw new RuntimeException('The Netmiko bridge could not be started.');
        }

        fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $result = json_decode($stdout ?: '', true);
        if (! is_array($result)) {
            report(new RuntimeException(trim($stderr) ?: 'Netmiko returned an invalid response.'));
            throw new RuntimeException('The Netmiko bridge returned an invalid response.');
        }
        if ($exitCode !== 0 || ($result['ok'] ?? false) !== true) {
            throw new RuntimeException((string) ($result['message'] ?? 'The router operation failed.'));
        }

        return $result;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function endpoint(string $endpoint): array
    {
        $endpoint = trim($endpoint);
        $host = $endpoint;
        $port = 22;

        if (str_starts_with($endpoint, '[')) {
            $closingBracket = strpos($endpoint, ']');
            if ($closingBracket === false) {
                throw new RuntimeException('Enter a valid SSH endpoint, such as 10.0.0.1 or 10.0.0.1:22.');
            }
            $host = substr($endpoint, 1, $closingBracket - 1);
            $portText = substr($endpoint, $closingBracket + 1);
            if (str_starts_with($portText, ':')) {
                $port = (int) substr($portText, 1);
            }
        } elseif (substr_count($endpoint, ':') === 1) {
            [$host, $portText] = explode(':', $endpoint, 2);
            $port = (int) $portText;
        }

        if ($host === '' || $port < 1 || $port > 65535 || (filter_var($host, FILTER_VALIDATE_IP) === false && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false)) {
            throw new RuntimeException('Enter a valid SSH endpoint, such as 10.0.0.1 or 10.0.0.1:22.');
        }

        return [$host, $port];
    }
}
