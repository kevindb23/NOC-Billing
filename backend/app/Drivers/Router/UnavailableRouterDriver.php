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
        $supported = $this->supports('connection_test');

        return new ConnectionResult(
            status: $supported
                ? ConnectionResult::STATUS_NOT_CONFIGURED
                : ConnectionResult::STATUS_UNSUPPORTED,
            driver: $this->identifier(),
            capabilities: $this->capabilities(),
            message: $supported
                ? 'No router transport is configured.'
                : 'Connection testing is not supported by this driver.',
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function getSystemInfo(Router $router): DeviceStatusResult
    {
        $supported = $this->supports('system_info');

        return new DeviceStatusResult(
            status: $supported
                ? DeviceStatusResult::STATUS_NOT_CONFIGURED
                : DeviceStatusResult::STATUS_UNSUPPORTED,
            driver: $this->identifier(),
            vendor: $this->driverVendor,
            message: $supported
                ? 'No router transport is configured.'
                : 'System information is not supported by this driver.',
            checkedAt: CarbonImmutable::now(),
        );
    }

    private function supports(string $capability): bool
    {
        return in_array($capability, $this->driverCapabilities, true);
    }
}
