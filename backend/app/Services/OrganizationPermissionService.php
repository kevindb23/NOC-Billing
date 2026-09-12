<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

class OrganizationPermissionService
{
    public function userCan(User $user, Organization $organization, string $permission): bool
    {
        if ($this->isSuperAdmin($user, $organization)) {
            return true;
        }

        return $this->permissionsFor($user, $organization)->contains('name', $permission);
    }

    public function permissionsFor(User $user, Organization $organization): Collection
    {
        if (! $user->organizations()
            ->whereKey($organization->getKey())
            ->wherePivot('status', 'active')
            ->exists()) {
            return collect();
        }

        if ($this->isSuperAdmin($user, $organization)) {
            return Permission::query()->get();
        }

        return $user->rolesForOrganization($organization)
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id')
            ->values();
    }

    public function isSuperAdmin(User $user, Organization $organization): bool
    {
        if (! $user->organizations()
            ->whereKey($organization->getKey())
            ->wherePivot('status', 'active')
            ->exists()) {
            return false;
        }

        return $user->rolesForOrganization($organization)
            ->whereRaw("lower(trim(roles.name)) in ('admin', 'administrator', 'superadmin', 'super admin', 'super administrator')")
            ->exists();
    }
}
