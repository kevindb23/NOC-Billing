<?php

namespace App\Contracts;

use App\DTO\Router\ConnectionResult;
use App\DTO\Router\DeviceStatusResult;
use App\Models\Router;

interface RouterDriverInterface
{
    public function identifier(): string;

    /** @return list<string> */
    public function capabilities(): array;

    public function testConnection(Router $router): ConnectionResult;

    public function getSystemInfo(Router $router): DeviceStatusResult;
}
