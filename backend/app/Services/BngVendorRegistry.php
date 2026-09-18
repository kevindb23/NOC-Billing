<?php

namespace App\Services;

final class BngVendorRegistry
{
    private const DRIVERS = [
        'linux' => ['label' => 'Linux BNG driver', 'netmiko_device_type' => 'linux', 'accel_ppp' => true],
        'mikrotik' => ['label' => 'MikroTik BNG driver', 'netmiko_device_type' => 'mikrotik_routeros', 'accel_ppp' => false],
    ];

    public static function driver(string $vendor): array
    {
        return self::DRIVERS[strtolower($vendor)] ?? throw new \InvalidArgumentException('The selected BNG vendor is not supported.');
    }
}
