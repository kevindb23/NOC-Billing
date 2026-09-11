<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = Permission::query()
            ->orderBy('name')
            ->get(['name'])
            ->groupBy(fn (Permission $permission): string => str($permission->name)->beforeLast('.')->toString())
            ->map(fn ($permissions, string $group): array => [
                'group' => $group,
                'permissions' => $permissions->map(fn (Permission $permission): array => [
                    'name' => $permission->name,
                    'action' => str($permission->name)->afterLast('.')->toString(),
                ])->values()->all(),
            ])
            ->values();

        return response()->json(['data' => $groups]);
    }
}
