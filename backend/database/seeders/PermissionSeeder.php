<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'users.*',
            'roles.*',
            'audit-logs.view',
            'dashboard.view',
            'billing.view',
            'billing.manage',
            'network.view',
            'network.manage',
            'system.view',
            'system.manage',
        ] as $name) {
            Permission::updateOrCreate(['name' => $name], ['guard_name' => 'api']);
        }
    }
}
