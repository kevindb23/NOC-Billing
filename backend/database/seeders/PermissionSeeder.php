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
            'billing-statements.create',
            'billing-statements.delete',
            'billing-statements.export',
            'billing-statements.update',
            'billing-statements.view',
            'branding.update',
            'branding.view',
            'gcash.create',
            'gcash.delete',
            'gcash.export',
            'gcash.review',
            'gcash.update',
            'gcash.view',
            'dashboard.create',
            'dashboard.delete',
            'dashboard.export',
            'dashboard.update',
            'dashboard.view',
            'email.test',
            'email.update',
            'email.view',
            'api-tokens.create',
            'api-tokens.delete',
            'api-tokens.view',
            'network.create',
            'network.delete',
            'network.export',
            'network.update',
            'network.view',
            'notifications.test',
            'notifications.update',
            'notifications.view',
            'paymongo.test',
            'paymongo.update',
            'paymongo.view',
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
            'routers.credentials.view',
            'routers.credentials.manage',
            'routers.operations.view',
            'routers.monitor',
            'routers.configuration.preview',
            'routers.configuration.apply',
            'routers.configuration.commit',
            'routers.configuration.rollback',
            'system.create',
            'system.delete',
            'system.export',
            'system.update',
            'system.view',
            'subscriptions.create',
            'subscriptions.delete',
            'subscriptions.export',
            'subscriptions.update',
            'subscriptions.view',
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
