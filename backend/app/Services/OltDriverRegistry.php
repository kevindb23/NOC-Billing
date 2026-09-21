<?php

namespace App\Services;

use InvalidArgumentException;

final class OltDriverRegistry
{
    public static function driver(string $vendor): OltDriver
    {
        return match (strtolower($vendor)) {
            'huawei' => app(HuaweiOltDriver::class),
            'hsgq' => app(HsgqOltDriver::class),
            default => throw new InvalidArgumentException("No OLT driver is registered for {$vendor}."),
        };
    }
}
