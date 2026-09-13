<?php

namespace App\Http\Middleware;

use App\Services\RouterOperationService;
use App\Services\RouterPermissionAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RouterOperationPermission
{
    public function __construct(private readonly RouterPermissionAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next, string $context = 'operation'): Response
    {
        $operation = (string) $request->input('operation');
        $user = $request->user();
        $permissions = RouterOperationService::permissionCandidates($operation, $context);

        if (! $user || ! $this->authorizer->allows($user, $permissions)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        if ($context !== 'history') {
            $request->attributes->set('router_operation_type', RouterOperationService::isConfiguration($operation) ? 'configuration' : 'monitoring');
        }

        return $next($request);
    }
}
