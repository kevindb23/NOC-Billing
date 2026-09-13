<?php

namespace Tests\Unit;

use App\Contracts\RouterDriverInterface;
use App\Drivers\Router\CiscoRouterDriver;
use App\Drivers\Router\JuniperRouterDriver;
use App\Drivers\Router\LinuxFrrRouterDriver;
use App\Drivers\Router\MikroTikRouterDriver;
use App\Drivers\Router\UnavailableRouterDriver;
use App\DTO\Router\ConnectionResult;
use App\DTO\Router\DeviceStatusResult;
use App\Models\Router;
use App\Services\RouterDriverRegistry;
use App\Services\RouterManager;
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
            status: ConnectionResult::STATUS_UNSUPPORTED,
            driver: $this->identifier(),
            capabilities: $this->capabilities(),
            message: 'Connection testing is not supported by this driver.',
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

final class DelegatingTestRouterDriver implements RouterDriverInterface
{
    public int $connectionCalls = 0;

    public int $systemInfoCalls = 0;

    public function identifier(): string
    {
        return 'fake_router';
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return ['connection_test', 'system_info'];
    }

    public function testConnection(Router $router): ConnectionResult
    {
        $this->connectionCalls++;

        return new ConnectionResult(
            status: ConnectionResult::STATUS_NOT_CONFIGURED,
            driver: $this->identifier(),
            capabilities: $this->capabilities(),
            message: 'Fake connection test delegated.',
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function getSystemInfo(Router $router): DeviceStatusResult
    {
        $this->systemInfoCalls++;

        return new DeviceStatusResult(
            status: DeviceStatusResult::STATUS_NOT_CONFIGURED,
            driver: $this->identifier(),
            message: 'Fake system info delegated.',
            checkedAt: CarbonImmutable::now(),
        );
    }
}

class RouterDriverRegistryTest extends TestCase
{
    public function test_initial_vendor_drivers_resolve_by_explicit_identifier(): void
    {
        $registry = new RouterDriverRegistry;

        foreach ([
            'mikrotik_router',
            'juniper_router',
            'cisco_router',
            'linux_frr_router',
        ] as $identifier) {
            $driver = $registry->resolve($identifier);

            $this->assertInstanceOf(RouterDriverInterface::class, $driver);
            $this->assertSame($identifier, $driver->identifier());
            $this->assertNotEmpty($driver->capabilities());
        }
    }

    public function test_unknown_driver_identifier_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown router driver [unknown_router].');

        (new RouterDriverRegistry)->resolve('unknown_router');
    }

    public function test_every_registered_driver_returns_normalized_not_configured_results(): void
    {
        $registry = new RouterDriverRegistry;
        $manager = new RouterManager($registry);
        $router = new Router([
            'name' => 'Test router',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'mock',
        ]);

        foreach ($registry->identifiers() as $identifier) {
            $router->driver = $identifier;

            $connection = $manager->testConnection($router)->toArray();
            $systemInfo = $manager->getSystemInfo($router)->toArray();

            $this->assertSame('not_configured', $connection['status']);
            $this->assertSame($identifier, $connection['driver']);
            $this->assertSame('not_configured', $systemInfo['status']);
            $this->assertSame($identifier, $systemInfo['driver']);
        }
    }

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

    public function test_initial_vendor_registrations_advertise_required_operations(): void
    {
        $registry = new RouterDriverRegistry;

        foreach ([
            'mikrotik_router' => MikroTikRouterDriver::class,
            'juniper_router' => JuniperRouterDriver::class,
            'cisco_router' => CiscoRouterDriver::class,
            'linux_frr_router' => LinuxFrrRouterDriver::class,
        ] as $identifier => $driverClass) {
            $driver = $registry->resolve($identifier);

            $this->assertInstanceOf($driverClass, $driver);
            $this->assertContains('connection_test', $driver->capabilities());
            $this->assertContains('system_info', $driver->capabilities());
        }
    }

    public function test_unavailable_driver_returns_unsupported_for_omitted_capabilities(): void
    {
        $router = new Router(['name' => 'Test router']);
        $connectionOnly = new UnavailableRouterDriver('connection_only', 'test', ['connection_test']);
        $systemInfoOnly = new UnavailableRouterDriver('system_info_only', 'test', ['system_info']);

        $this->assertSame('not_configured', $connectionOnly->testConnection($router)->toArray()['status']);
        $this->assertSame('unsupported', $connectionOnly->getSystemInfo($router)->toArray()['status']);
        $this->assertSame('unsupported', $systemInfoOnly->testConnection($router)->toArray()['status']);
        $this->assertSame('not_configured', $systemInfoOnly->getSystemInfo($router)->toArray()['status']);
    }

    public function test_manager_delegates_operations_to_an_injected_driver(): void
    {
        $driver = new DelegatingTestRouterDriver;
        $manager = new RouterManager(new RouterDriverRegistry(['fake_router' => $driver]));
        $router = new Router(['name' => 'Test router', 'driver' => 'fake_router']);

        $this->assertSame($driver, $manager->driverFor($router));
        $this->assertSame('fake_router', $manager->testConnection($router)->toArray()['driver']);
        $this->assertSame('fake_router', $manager->getSystemInfo($router)->toArray()['driver']);
        $this->assertSame(1, $driver->connectionCalls);
        $this->assertSame(1, $driver->systemInfoCalls);
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
        $this->assertSame('unsupported', $driver->testConnection($router)->toArray()['status']);
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
