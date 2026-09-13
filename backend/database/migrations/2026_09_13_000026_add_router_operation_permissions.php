<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'routers.credentials.view',
        'routers.credentials.manage',
        'routers.operations.view',
        'routers.monitor',
        'routers.configuration.preview',
        'routers.configuration.apply',
        'routers.configuration.commit',
        'routers.configuration.rollback',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');
        $administratorRoleIds = DB::table('roles')
            ->whereRaw("lower(trim(name)) in ('admin', 'administrator', 'superadmin', 'super admin', 'super administrator')")
            ->pluck('id');

        foreach ($administratorRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
