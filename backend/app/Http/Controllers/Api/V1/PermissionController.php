<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $actionOrder = [
            'view' => 0,
            'create' => 1,
            'update' => 2,
            'delete' => 3,
            'test' => 4,
            'export' => 5,
            'manage' => 6,
            'monitor' => 7,
            'preview' => 8,
            'apply' => 9,
            'commit' => 10,
            'rollback' => 11,
        ];
        $permissions = Permission::query()->get(['id', 'name']);
        $labels = [
            'api-tokens' => 'API Tokens',
            'audit-logs' => 'Audit Logs',
            'billing-accounts' => 'Billing Accounts',
            'billing-statements' => 'Billing Statements',
            'subscriber-services' => 'Subscriber Services',
            'paymongo' => 'Paymongo',
            'gcash' => 'GCash',
            'routers' => 'Routers',
            'olts' => 'OLTs',
        ];
        $legacyGroups = collect(['Dashboard', 'Billing', 'Network', 'System', 'Users', 'Roles', 'Branding', 'Audit Logs'])
            ->mapWithKeys(fn (string $group): array => [$group => collect()]);
        $grouped = $permissions->groupBy(fn (Permission $permission): string => str($permission->name)->beforeLast('.')->toString());
        $groups = $grouped->map(function ($items, string $prefix) use ($actionOrder, $labels): array {
            $group = $labels[$prefix] ?? str($prefix)->replace('-', ' ')->title()->toString();
            $sorted = $items->sort(function (Permission $left, Permission $right) use ($actionOrder): int {
                $leftAction = str($left->name)->afterLast('.')->toString();
                $rightAction = str($right->name)->afterLast('.')->toString();
                $actionComparison = ($actionOrder[$leftAction] ?? PHP_INT_MAX) <=> ($actionOrder[$rightAction] ?? PHP_INT_MAX);

                return $actionComparison !== 0 ? $actionComparison : $left->name <=> $right->name;
            })->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'action' => str($permission->name)->afterLast('.')->toString(),
            ])->values()->all();

            return ['group' => $group, 'permissions' => $sorted];
        })->sortBy('group')->values();

        $groups = $groups->concat(
            $legacyGroups
                ->filter(fn ($items, $group) => ! $groups->contains('group', $group))
                ->map(fn ($items, $group): array => ['group' => $group, 'permissions' => []])
                ->values()
        )->sortBy('group')->values();

        return response()->json(['data' => $groups]);
    }
}
