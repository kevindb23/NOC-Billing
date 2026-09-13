<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Services\RouterOperationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RouterOperationPermission
{
    public function handle(Request $request, Closure $next, string $context = 'operation'): Response
    {
        $operation = (string) $request->input('operation');
        $user = $request->user();
        $permissions = RouterOperationService::permissionCandidates($operation, $context);

        if (! $user || ! $this->hasPermission($user->getAuthIdentifier(), $permissions)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        if ($context !== 'history') {
            $request->attributes->set('router_operation_type', RouterOperationService::isConfiguration($operation) ? 'configuration' : 'monitoring');
        }

        return $next($request);
    }

    /** @param list<string> $permissions */
    private function hasPermission(int|string|null $userId, array $permissions): bool
    {
        if ($userId === null || $permissions === []) {
            return false;
        }

        return DB::table('role_permissions')
            ->join('role_assignments', 'role_assignments.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_assignments.user_id', $userId)
            ->whereIn('permissions.name', $permissions)
            ->exists();
    }
}
