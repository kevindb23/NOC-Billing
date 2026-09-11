<?php

namespace App\Http\Middleware;

use App\Services\OrganizationPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private OrganizationPermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $organization = $request->attributes->get('organization');
        $user = $request->user();

        if (! $organization || ! $user || ! $this->permissions->userCan($user, $organization, $permission)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $next($request);
    }
}
