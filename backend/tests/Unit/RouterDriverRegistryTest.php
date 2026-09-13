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
}
