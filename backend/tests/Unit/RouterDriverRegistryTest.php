<?php

namespace Tests\Unit;

use App\Contracts\RouterDriverInterface;
use App\DTO\Router\ConnectionResult;
use App\DTO\Router\DeviceStatusResult;
use App\Models\Router;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TestRouterDriver implements RouterDriverInterface
{
    public function identifier(): string
    {
        return 'mock_router';
    }

    public function capabilities(): array
    {
        return ['system_info'];
    }

    public function testConnection(Router $router): ConnectionResult
    {
        return new ConnectionResult(
            status: ConnectionResult::STATUS_NOT_CONFIGURED,
            driver: $this->identifier(),
            capabilities: $this->capabilities(),
            message: 'No router transport is configured.',
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function getSystemInfo(Router $router): DeviceStatusResult
    {
        return new DeviceStatusResult(
            status: DeviceStatusResult::STATUS_NOT_CONFIGURED,
            driver: $this->identifier(),
            message: 'No router transport is configured.',
            checkedAt: CarbonImmutable::now(),
        );
    }
}

class RouterDriverRegistryTest extends TestCase
{
    public function test_connection_result_serializes_to_a_stable_normalized_shape_and_redacts_secrets(): void
    {
        $checkedAt = CarbonImmutable::parse('2026-09-13 12:00:00', 'UTC');

        $result = new ConnectionResult(
            status: ConnectionResult::STATUS_CONNECTED,
            driver: 'mikrotik_router',
            capabilities: ['system_info', 'interfaces'],
            message: 'Router connection verified.',
            checkedAt: $checkedAt,
            details: [
                'latency_ms' => 12,
                'password' => 'do-not-return',
                'nested' => ['api_token' => 'do-not-return'],
            ],
        );

        $this->assertSame([
            'status' => 'connected',
            'driver' => 'mikrotik_router',
            'capabilities' => ['system_info', 'interfaces'],
            'message' => 'Router connection verified.',
            'checked_at' => '2026-09-13T12:00:00+00:00',
            'details' => [
                'latency_ms' => 12,
                'password' => '[REDACTED]',
                'nested' => ['api_token' => '[REDACTED]'],
            ],
        ], $result->toArray());
    }

    public function test_device_status_result_serializes_normalized_system_fields(): void
    {
        $result = new DeviceStatusResult(
            status: DeviceStatusResult::STATUS_CONNECTED,
            driver: 'cisco_router',
            vendor: 'cisco',
            hostname: 'edge-router.example.test',
            model: 'ASR1001-X',
            serialNumber: 'CISCO-123',
            softwareVersion: '17.9.4',
            uptimeSeconds: 86400,
            message: 'System information retrieved.',
            checkedAt: CarbonImmutable::parse('2026-09-13 12:00:00', 'UTC'),
        );

        $this->assertSame([
            'status' => 'connected',
            'driver' => 'cisco_router',
            'vendor' => 'cisco',
            'hostname' => 'edge-router.example.test',
            'model' => 'ASR1001-X',
            'serial_number' => 'CISCO-123',
            'software_version' => '17.9.4',
            'uptime_seconds' => 86400,
            'message' => 'System information retrieved.',
            'checked_at' => '2026-09-13T12:00:00+00:00',
            'details' => null,
        ], $result->toArray());
    }

    public function test_connection_result_redacts_nested_credential_payloads(): void
    {
        $result = new ConnectionResult(
            status: ConnectionResult::STATUS_CONNECTED,
            driver: 'juniper_router',
            capabilities: ['system_info'],
            message: 'Router connection verified.',
            checkedAt: CarbonImmutable::now(),
            details: [
                'authorization' => 'Bearer connection-secret',
                'passphrase' => 'router-passphrase',
                'headers' => ['Authorization' => 'Basic header-secret'],
                'certificate' => ['pem' => 'certificate-secret'],
                'private_key' => 'private-key-secret',
                'nested' => ['client_secret' => 'nested-secret', 'latency_ms' => 8],
                'tls' => [
                    'username' => 'router-user',
                    'pass' => 'router-pass',
                    'endpoint' => 'tls://router.example.test',
                ],
                'ssl' => [
                    'username' => 'ssl-user',
                    'pass' => 'ssl-pass',
                    'endpoint' => 'ssl://router.example.test',
                ],
            ],
        );

        $details = $result->toArray()['details'];

        $this->assertSame('[REDACTED]', $details['authorization']);
        $this->assertSame('[REDACTED]', $details['passphrase']);
        $this->assertSame('[REDACTED]', $details['headers']['Authorization']);
        $this->assertSame('[REDACTED]', $details['certificate']['pem']);
        $this->assertSame('[REDACTED]', $details['private_key']);
        $this->assertSame('[REDACTED]', $details['nested']['client_secret']);
        $this->assertSame(8, $details['nested']['latency_ms']);
        $this->assertSame('[REDACTED]', $details['tls']['username']);
        $this->assertSame('[REDACTED]', $details['tls']['pass']);
        $this->assertSame('[REDACTED]', $details['tls']['endpoint']);
        $this->assertSame('[REDACTED]', $details['ssl']['username']);
        $this->assertSame('[REDACTED]', $details['ssl']['pass']);
        $this->assertSame('[REDACTED]', $details['ssl']['endpoint']);
    }

    public function test_device_status_result_redacts_nested_credential_payloads(): void
    {
        $result = new DeviceStatusResult(
            status: DeviceStatusResult::STATUS_CONNECTED,
            driver: 'cisco_router',
            message: 'System information retrieved.',
            checkedAt: CarbonImmutable::now(),
            details: [
                'authorization_header' => 'Bearer status-secret',
                'passphrase' => 'status-passphrase',
                'tls' => [
                    'headers' => ['X-Api-Key' => 'header-secret'],
                    'certificate_chain' => ['certificate-secret'],
                    'client_private_key' => 'key-secret',
                    'username' => 'tls-user',
                    'pass' => 'tls-pass',
                    'endpoint' => 'tls://status.example.test',
                ],
                'ssl' => [
                    'username' => 'ssl-user',
                    'pass' => 'ssl-pass',
                    'endpoint' => 'ssl://status.example.test',
                ],
                'safe' => ['uptime_source' => 'snmp'],
            ],
        );

        $details = $result->toArray()['details'];

        $this->assertSame('[REDACTED]', $details['authorization_header']);
        $this->assertSame('[REDACTED]', $details['passphrase']);
        $this->assertSame('[REDACTED]', $details['tls']['headers']['X-Api-Key']);
        $this->assertSame('[REDACTED]', $details['tls']['certificate_chain']);
        $this->assertSame('[REDACTED]', $details['tls']['client_private_key']);
        $this->assertSame('[REDACTED]', $details['tls']['username']);
        $this->assertSame('[REDACTED]', $details['tls']['pass']);
        $this->assertSame('[REDACTED]', $details['tls']['endpoint']);
        $this->assertSame('[REDACTED]', $details['ssl']['username']);
        $this->assertSame('[REDACTED]', $details['ssl']['pass']);
        $this->assertSame('[REDACTED]', $details['ssl']['endpoint']);
        $this->assertSame('snmp', $details['safe']['uptime_source']);
    }

    public function test_driver_contract_exposes_normalized_operations(): void
    {
        $driver = new TestRouterDriver;

        $router = new Router(['name' => 'Test router']);

        $this->assertSame('mock_router', $driver->identifier());
        $this->assertSame(['system_info'], $driver->capabilities());
        $this->assertSame('not_configured', $driver->testConnection($router)->toArray()['status']);
        $this->assertSame('not_configured', $driver->getSystemInfo($router)->toArray()['status']);
    }

    public function test_result_rejects_unknown_status_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConnectionResult(
            status: 'online',
            driver: 'mock_router',
            capabilities: [],
            message: 'Invalid result.',
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function test_device_status_result_rejects_unknown_status_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeviceStatusResult(
            status: 'online',
            driver: 'mock_router',
            message: 'Invalid result.',
            checkedAt: CarbonImmutable::now(),
        );
    }
}
