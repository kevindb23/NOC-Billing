<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

class OrganizationPermissionService
{
    public function userCan(User $user, string $permission): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->permissionsFor($user)->contains('name', $permission);
    }

    public function permissionsFor(User $user): Collection
    {
        if ($this->isSuperAdmin($user)) {
            return Permission::query()->get();
        }

        return $user->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id')
            ->values();
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->roles()
            ->whereRaw("lower(trim(roles.name)) in ('admin', 'administrator', 'superadmin', 'super admin', 'super administrator')")
            ->exists();
    }
}
