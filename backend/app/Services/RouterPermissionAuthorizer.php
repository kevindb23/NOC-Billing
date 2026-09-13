<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RouterPermissionAuthorizer
{
    /**
     * @param list<string> $permissions
     */
    public function allows(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        return DB::table('role_assignments')
            ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
            ->leftJoin('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->leftJoin('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_assignments.user_id', $user->getAuthIdentifier())
            ->where(function ($query) use ($permissions): void {
                $query
                    ->whereIn('permissions.name', $permissions)
                    ->orWhereRaw("lower(trim(roles.name)) in ('admin', 'administrator', 'superadmin', 'super admin', 'super administrator')");
            })
            ->exists();
    }
}
