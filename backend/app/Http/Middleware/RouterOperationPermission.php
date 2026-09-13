<?php

namespace App\Http\Middleware;

use App\Services\OrganizationPermissionService;
use App\Services\RouterOperationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RouterOperationPermission
{
    public function __construct(private readonly OrganizationPermissionService $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $operation = (string) $request->input('operation');
        $organization = $request->attributes->get('organization');
        $user = $request->user();
        $permission = RouterOperationService::permissionFor($operation);

        if (! $organization || ! $user || ! $this->permissions->userCan($user, $organization, $permission)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $request->attributes->set('router_operation_type', RouterOperationService::isConfiguration($operation) ? 'configuration' : 'monitoring');

        return $next($request);
    }
}
