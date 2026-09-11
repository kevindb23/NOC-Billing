<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OrganizationPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private OrganizationPermissionService $permissions) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = \App\Models\User::where('email', $credentials['email'])->where('status', 'active')->first();
        abort_unless($user && Hash::check($credentials['password'], $user->password), 422, 'The provided credentials are invalid.');
        $organization = $user->organizations()->wherePivot('status', 'active')->orderByDesc('organization_user.is_default')->first();
        abort_unless($organization, 403, 'The user has no active organization membership.');
        $token = $user->createToken('billing-portal')->plainTextToken;
        return response()->json(['data' => [
            'token' => $token,
            'user' => $user,
            'organization' => $organization,
            'permissions' => $this->permissions->permissionsFor($user, $organization)->pluck('name')->sort()->values()->all(),
        ]]);
    }

    public function me(Request $request): JsonResponse
    {
        $organization = $request->attributes->get('organization');
        return response()->json(['data' => [
            'user' => $request->user(),
            'organization' => $organization,
            'permissions' => $this->permissions->permissionsFor($request->user(), $organization)->pluck('name')->sort()->values()->all(),
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['data' => ['message' => 'Signed out.']]);
    }
}
