<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcsServerRequest;
use App\Models\AcsServer;
use App\Services\AcsServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AcsServerController extends Controller
{
    public function index(): JsonResponse
    {
        $requestId = (string) Str::uuid();
        $started = hrtime(true);
        Log::info('ACS inventory requested', ['request_id' => $requestId]);

        $servers = AcsServer::query()
            ->select([
                'id', 'public_id', 'name', 'api_url', 'api_username', 'transport', 'status',
                'ssh_username', 'ssh_port', 'last_ssh_tested_at', 'last_api_tested_at', 'last_error',
            ])
            ->latest('id')
            ->get()
            ->map(static fn (AcsServer $server): array => [
                'id' => $server->id,
                'public_id' => $server->public_id,
                'name' => $server->name,
                'api_url' => $server->api_url,
                'api_username' => $server->api_username,
                'transport' => $server->transport,
                'status' => $server->status,
                'ssh_username' => $server->ssh_username,
                'ssh_port' => $server->ssh_port,
                'last_ssh_tested_at' => $server->last_ssh_tested_at?->toISOString(),
                'last_api_tested_at' => $server->last_api_tested_at?->toISOString(),
                'last_error' => $server->last_error,
            ])
            ->values()
            ->all();
        $response = response()->json(['data' => $servers]);
        $durationMs = round((hrtime(true) - $started) / 1_000_000, 2);

        Log::info('ACS inventory loaded', [
            'request_id' => $requestId,
            'record_count' => count($servers),
            'duration_ms' => $durationMs,
        ]);

        return $response->header('X-Request-ID', $requestId)
            ->header('Server-Timing', "acs_inventory;dur={$durationMs}");
    }
    public function store(StoreAcsServerRequest $request): JsonResponse { return response()->json(['data' => AcsServer::create($request->validated())->fresh()], Response::HTTP_CREATED); }
    public function show(string $publicId): JsonResponse { return response()->json(['data' => AcsServer::query()->where('public_id', $publicId)->firstOrFail()]); }
    public function update(StoreAcsServerRequest $request, string $publicId): JsonResponse { $server = AcsServer::query()->where('public_id', $publicId)->firstOrFail(); $values = $request->validated(); if (blank($values['api_password'] ?? null)) unset($values['api_password']); if (blank($values['ssh_password'] ?? null)) unset($values['ssh_password']); $server->update($values); return response()->json(['data' => $server->fresh()]); }
    public function destroy(Request $request, string $publicId): Response { $server = AcsServer::withTrashed()->where('public_id', $publicId)->firstOrFail(); $request->boolean('permanent') ? $server->forceDelete() : $server->delete(); return response()->noContent(); }
    public function testSsh(StoreAcsServerRequest $request, AcsServerService $service): JsonResponse { return $this->runTest(fn () => $service->testSsh($request->validated())); }
    public function testApi(StoreAcsServerRequest $request, AcsServerService $service): JsonResponse { return $this->runTest(fn () => $service->testApi($request->validated())); }
    public function testStoredSsh(string $publicId, AcsServerService $service): JsonResponse { return $this->runStoredTest(AcsServer::query()->where('public_id', $publicId)->firstOrFail(), $service, 'ssh'); }
    public function testStoredApi(string $publicId, AcsServerService $service): JsonResponse { return $this->runStoredTest(AcsServer::query()->where('public_id', $publicId)->firstOrFail(), $service, 'api'); }
    public function passwordComplexity(string $publicId, AcsServerService $service): JsonResponse { try { return response()->json(['data' => $service->getPasswordComplexity(AcsServer::query()->where('public_id', $publicId)->firstOrFail())]); } catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); } }
    public function updatePasswordComplexity(Request $request, string $publicId, AcsServerService $service): JsonResponse { $values = $request->validate(['minimum_password_length' => ['required', 'integer', 'min:1', 'max:255']]); try { return response()->json(['data' => $service->updatePasswordComplexity(AcsServer::query()->where('public_id', $publicId)->firstOrFail(), (int) $values['minimum_password_length'])]); } catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); } }
    private function runStoredTest(AcsServer $server, AcsServerService $service, string $kind): JsonResponse { try { $result = $kind === 'ssh' ? $service->testStoredSsh($server) : $service->testStoredApi($server); $server->update([$kind === 'ssh' ? 'last_ssh_tested_at' : 'last_api_tested_at' => now(), 'last_error' => null]); return response()->json(['data' => $result]); } catch (RuntimeException $exception) { $server->update([$kind === 'ssh' ? 'last_ssh_tested_at' : 'last_api_tested_at' => now(), 'last_error' => $exception->getMessage()]); return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); } }
    private function runTest(callable $callback): JsonResponse { try { return response()->json(['data' => $callback()]); } catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); } }
}
