<?php

namespace App\Services;

final class RouterVendorRegistry
{
    private const VENDORS = [
        'mikrotik' => [
            'label' => 'MikroTik',
            'transports' => ['ssh'],
            'netmiko_device_type' => 'mikrotik_routeros',
            'commands' => [
                'system_information' => '/system resource print',
            ],
        ],
        'juniper' => [
            'label' => 'Juniper',
            'transports' => ['ssh'],
            'netmiko_device_type' => 'juniper_junos',
            'commands' => [
                'system_information' => 'show version | no-more',
            ],
        ],
    ];

    public static function identifiers(): array
    {
        return array_keys(self::VENDORS);
    }

    public static function supports(string $vendor, string $transport): bool
    {
        return in_array($transport, self::VENDORS[$vendor]['transports'] ?? [], true);
    }

    public static function driver(string $vendor): array
    {
        return self::VENDORS[$vendor] ?? throw new \InvalidArgumentException('The selected router vendor is not supported.');
    }

    public static function command(string $vendor, string $operation): ?string
    {
        return self::driver($vendor)['commands'][$operation] ?? null;
    }
}
