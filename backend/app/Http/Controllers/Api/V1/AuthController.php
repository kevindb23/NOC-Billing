<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OrganizationPermissionService;
use App\Services\OrganizationBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private OrganizationPermissionService $permissions, private OrganizationBrandingService $branding) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = \App\Models\User::where('email', $credentials['email'])->where('status', 'active')->first();
        abort_unless($user && Hash::check($credentials['password'], $user->password), 422, 'The provided credentials are invalid.');
        $accessToken = $user->createToken('billing-portal');
        $token = $accessToken->plainTextToken;

        return response()->json(['data' => [
            'token' => $token,
            'user' => $user,
            'permissions' => $this->permissions->permissionsFor($user)->pluck('name')->sort()->values()->all(),
            'is_superadmin' => $this->permissions->isSuperAdmin($user),
            'branding' => $this->branding->resolved(),
        ]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'user' => $request->user(),
            'permissions' => $this->permissions->permissionsFor($request->user())->pluck('name')->sort()->values()->all(),
            'is_superadmin' => $this->permissions->isSuperAdmin($request->user()),
            'branding' => $this->branding->resolved(),
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['data' => ['message' => 'Signed out.']]);
    }
}
