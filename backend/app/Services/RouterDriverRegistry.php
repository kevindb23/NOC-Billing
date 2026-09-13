<?php

namespace App\Services;

use App\Contracts\RouterDriverInterface;
use App\Drivers\Router\CiscoRouterDriver;
use App\Drivers\Router\JuniperRouterDriver;
use App\Drivers\Router\LinuxFrrRouterDriver;
use App\Drivers\Router\MikroTikRouterDriver;
use InvalidArgumentException;

class RouterDriverRegistry
{
    /** @var array<string, RouterDriverInterface> */
    private array $drivers;

    /** @param array<string, RouterDriverInterface>|null $drivers */
    public function __construct(?array $drivers = null)
    {
        $this->drivers = $drivers ?? [
            'mikrotik_router' => new MikroTikRouterDriver,
            'juniper_router' => new JuniperRouterDriver,
            'cisco_router' => new CiscoRouterDriver,
            'linux_frr_router' => new LinuxFrrRouterDriver,
        ];
    }

    public function resolve(string $identifier): RouterDriverInterface
    {
        if (! isset($this->drivers[$identifier])) {
            throw new InvalidArgumentException("Unknown router driver [{$identifier}].");
        }

        return $this->drivers[$identifier];
    }

    /** @return list<string> */
    public function identifiers(): array
    {
        return array_keys($this->drivers);
    }
}
