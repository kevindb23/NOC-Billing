<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $groupDefinitions = [
            'Dashboard' => ['dashboard'],
            'Billing' => ['billing'],
            'Network' => ['network'],
            'Routers' => ['routers'],
            'System' => ['system'],
            'Users' => ['users'],
            'Roles' => ['roles'],
            'Branding' => ['branding'],
            'Audit Logs' => ['audit-logs'],
        ];
        $actionOrder = [
            'view' => 0,
            'create' => 1,
            'update' => 2,
            'delete' => 3,
            'export' => 4,
            'test' => 5,
            'manage' => 6,
            'monitor' => 7,
            'preview' => 8,
            'apply' => 9,
            'commit' => 10,
            'rollback' => 11,
        ];
        $permissions = Permission::query()->get(['id', 'name']);

        $groups = collect($groupDefinitions)->map(function (array $prefixes, string $group) use ($permissions, $actionOrder): array {
            $prefixOrder = array_flip($prefixes);
            $groupPermissions = $permissions
                ->filter(function (Permission $permission) use ($prefixOrder): bool {
                    $prefix = str($permission->name)->before('.')->toString();

                    return array_key_exists($prefix, $prefixOrder);
                })
                ->sort(function (Permission $left, Permission $right) use ($actionOrder, $prefixOrder): int {
                    $leftAction = str($left->name)->afterLast('.')->toString();
                    $rightAction = str($right->name)->afterLast('.')->toString();
                    $actionComparison = ($actionOrder[$leftAction] ?? PHP_INT_MAX) <=> ($actionOrder[$rightAction] ?? PHP_INT_MAX);

                    if ($actionComparison !== 0) {
                        return $actionComparison;
                    }

                    $leftPrefix = str($left->name)->before('.')->toString();
                    $rightPrefix = str($right->name)->before('.')->toString();

                    return ($prefixOrder[$leftPrefix] ?? PHP_INT_MAX) <=> ($prefixOrder[$rightPrefix] ?? PHP_INT_MAX);
                })
                ->map(fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'action' => str($permission->name)->afterLast('.')->toString(),
                ])
                ->values()
                ->all();

            return ['group' => $group, 'permissions' => $groupPermissions];
        })->values();

        return response()->json(['data' => $groups]);
    }
}
