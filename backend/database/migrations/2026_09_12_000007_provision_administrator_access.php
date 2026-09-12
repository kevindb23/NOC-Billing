<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['branding.view', 'branding.update'] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $permissionIds = DB::table('permissions')->pluck('id');
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
            ->whereIn('name', ['branding.view', 'branding.update'])
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
