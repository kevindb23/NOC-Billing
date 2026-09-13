<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'audit-logs.export',
            'audit-logs.view',
            'billing.create',
            'billing.delete',
            'billing.export',
            'billing.update',
            'billing.view',
            'branding.update',
            'branding.view',
            'dashboard.create',
            'dashboard.delete',
            'dashboard.export',
            'dashboard.update',
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
            'routers.create',
            'routers.delete',
            'routers.export',
            'routers.test',
            'routers.update',
            'routers.view',
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
        ];

        Permission::query()->whereNotIn('name', $permissions)->delete();

        foreach ($permissions as $name) {
            Permission::updateOrCreate(['name' => $name], ['guard_name' => 'api']);
        }
    }
}
