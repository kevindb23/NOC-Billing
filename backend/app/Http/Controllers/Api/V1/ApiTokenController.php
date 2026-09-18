<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateApiTokenRequest;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $tokens = PersonalAccessToken::query()->where('tokenable_type', $request->user()->getMorphClass())->where('tokenable_id', $request->user()->id)->latest()->get()->map(fn (PersonalAccessToken $token) => $this->present($token))->values();
        return response()->json(['data' => $tokens]);
    }

    public function store(CreateApiTokenRequest $request): JsonResponse
    {
        $abilities = $request->validated('abilities');
        abort_unless($abilities === ['*'] || collect($abilities)->every(fn (string $ability) => preg_match('/^[a-z0-9_-]+\.(view|create|update|delete|export|test)$/', $ability)), 422, 'One or more token scopes are invalid.');
        $expiresAt = $request->validated('expires_at') ? Carbon::parse($request->validated('expires_at'))->endOfDay() : null;
        $accessToken = $request->user()->createToken($request->validated('name'), $abilities, $expiresAt);
        $this->auditLogger->record($request, 'api_token.created', $accessToken->accessToken, [], ['name' => $accessToken->accessToken->name, 'abilities' => $abilities]);
        return response()->json(['data' => ['token' => $accessToken->plainTextToken, 'token_record' => $this->present($accessToken->accessToken)]], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $token = PersonalAccessToken::query()->whereKey($id)->where('tokenable_id', $request->user()->id)->firstOrFail();
        $this->auditLogger->record($request, 'api_token.revoked', $token, ['name' => $token->name], []);
        $token->delete();
        return response()->json(['data' => ['message' => 'API token revoked.']]);
    }

    private function present(PersonalAccessToken $token): array
    {
        return ['id' => $token->id, 'name' => $token->name, 'abilities' => $token->abilities, 'last_used_at' => $token->last_used_at, 'expires_at' => $token->expires_at, 'created_at' => $token->created_at];
    }
}
