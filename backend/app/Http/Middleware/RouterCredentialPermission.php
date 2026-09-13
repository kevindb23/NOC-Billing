<?php

namespace App\Http\Middleware;

use App\Services\RouterPermissionAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RouterCredentialPermission
{
    public function __construct(private readonly RouterPermissionAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next, string $context = 'view'): Response
    {
        $permission = $context === 'manage'
            ? 'routers.credentials.manage'
            : 'routers.credentials.view';
        $user = $request->user();

        if (! $user || ! $this->authorizer->allows($user, [$permission])) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $next($request);
    }
}
