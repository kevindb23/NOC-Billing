<?php

namespace App\Drivers\Router;

use App\Contracts\RouterDriverInterface;
use App\DTO\Router\ConnectionResult;
use App\DTO\Router\DeviceStatusResult;
use App\Models\Router;
use Carbon\CarbonImmutable;

class UnavailableRouterDriver implements RouterDriverInterface
{
    /** @param list<string> $capabilities */
    public function __construct(
        private readonly string $driverIdentifier,
        private readonly string $driverVendor,
        private readonly array $driverCapabilities = ['connection_test', 'system_info'],
    ) {}

    public function identifier(): string
    {
        return $this->driverIdentifier;
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return $this->driverCapabilities;
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
            vendor: $this->driverVendor,
            message: 'No router transport is configured.',
            checkedAt: CarbonImmutable::now(),
        );
    }
}
