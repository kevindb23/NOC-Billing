<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

class OrganizationPermissionService
{
    public function userCan(User $user, Organization $organization, string $permission): bool
    {
        return $this->permissionsFor($user, $organization)->contains('name', $permission);
    }

    public function permissionsFor(User $user, Organization $organization): Collection
    {
        return $user->rolesForOrganization($organization)
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id')
            ->values();
    }
}
