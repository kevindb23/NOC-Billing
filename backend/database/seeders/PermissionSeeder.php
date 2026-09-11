<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'audit-logs.export',
            'audit-logs.view',
            'billing.create',
            'billing.delete',
            'billing.export',
            'billing.update',
            'billing.view',
            'dashboard.export',
            'dashboard.view',
            'network.create',
            'network.delete',
            'network.export',
            'network.update',
            'network.view',
            'roles.create',
            'roles.delete',
            'roles.export',
            'roles.update',
            'roles.view',
            'system.create',
            'system.delete',
            'system.export',
            'system.update',
            'system.view',
            'users.create',
            'users.delete',
            'users.export',
            'users.update',
            'users.view',
        ] as $name) {
            Permission::updateOrCreate(['name' => $name], ['guard_name' => 'api']);
        }
    }
}
