<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organizationId = $request->header('X-Organization-Id');
        $membership = $user?->organizations()
            ->wherePivot('status', 'active')
            ->when($organizationId, fn ($query) => $query->where('organizations.public_id', $organizationId))
            ->orderByDesc('organization_user.is_default')
            ->first();

        abort_unless($membership, 403, 'No active organization membership was found.');
        $request->attributes->set('organization', $membership);

        return $next($request);
    }
}
