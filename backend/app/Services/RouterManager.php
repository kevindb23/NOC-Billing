<?php

namespace App\Services;

use App\Contracts\RouterDriverInterface;
use App\DTO\Router\ConnectionResult;
use App\DTO\Router\DeviceStatusResult;
use App\Models\Router;

class RouterManager
{
    public function __construct(private readonly RouterDriverRegistry $registry) {}

    public function driverFor(Router $router): RouterDriverInterface
    {
        return $this->registry->resolve($router->driver);
    }

    public function testConnection(Router $router): ConnectionResult
    {
        return $this->driverFor($router)->testConnection($router);
    }

    public function getSystemInfo(Router $router): DeviceStatusResult
    {
        return $this->driverFor($router)->getSystemInfo($router);
    }
}
