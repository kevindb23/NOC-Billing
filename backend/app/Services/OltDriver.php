<?php

namespace App\Services;

use App\Models\Olt;

interface OltDriver
{
    /** @return list<string> */
    public function createVlanCommands(array $vlan): array;

    /** @return list<string> */
    public function createQinqCommands(array $qinq): array;

    /** @return array<string, mixed> */
    public function provisioningPayload(string $type, array $values): array;

    /** @return array<string, mixed> */
    public function provisioningDeletePayload(string $type, array $values): array;

    public function deviceType(): string;
}
