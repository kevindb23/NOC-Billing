<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouterRequest;
use App\Http\Requests\UpdateRouterRequest;
use App\Models\Router;
use App\Services\AuditLogger;
use App\Services\RouterCredentialService;
use App\Services\RouterManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RouterController extends Controller
{
    public function __construct(
        private readonly RouterManager $routerManager,
        private readonly AuditLogger $auditLogger,
        private readonly RouterCredentialService $credentialService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $search = $request->string('search')->toString();
        $routers = Router::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('hostname', 'like', "%{$search}%")
                        ->orWhere('management_ip', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return response()->json(['data' => $routers]);
    }

    public function store(StoreRouterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $profile = $data['credential_profile'] ?? null;
        unset($data['credential_profile']);
        [$router, $credential] = DB::transaction(function () use ($request, $data, $profile): array {
            $router = new Router($data);
            $router->created_by = $request->user()->getAuthIdentifier();
            $router->capabilities = $this->driver($router)->capabilities();
            $router->save();
            $credential = is_array($profile) ? $this->credentialService->storeOrReplace($router, $profile) : null;
            $this->auditLogger->record($request, 'router.created', $router, [], [
                'result' => 'created',
                ...$this->snapshot($router),
                ...$this->credentialSnapshot($credential),
            ]);

            return [$router, $credential];
        });

        return response()->json([
            'data' => $this->resource($router->fresh(), $credential),
            'correlation_id' => $request->header('X-Request-Id'),
        ], Response::HTTP_CREATED);
    }

    public function show(string $publicId): JsonResponse
    {
        return response()->json(['data' => $this->router($publicId)]);
    }

    public function update(UpdateRouterRequest $request, string $publicId): JsonResponse
    {
        $router = $this->router($publicId);
        $oldSnapshot = $this->snapshot($router);
        $data = $request->validated();
        $profile = $data['credential_profile'] ?? null;
        unset($data['credential_profile']);
        [$router, $credential] = DB::transaction(function () use ($request, $router, $data, $profile, $oldSnapshot): array {
            $router->fill($data);
            $router->capabilities = $this->driver($router)->capabilities();
            $router->save();
            $credential = is_array($profile) ? $this->credentialService->storeOrReplace($router, $profile) : $router->primaryCredential()->first();
            $this->auditLogger->record($request, 'router.updated', $router, $oldSnapshot, [
                'result' => 'updated',
                ...$this->snapshot($router),
                ...$this->credentialSnapshot($credential),
            ]);

            return [$router, $credential];
        });

        return response()->json(['data' => $this->resource($router->fresh(), $credential)]);
    }

    public function destroy(Request $request, string $publicId): Response
    {
        $router = $this->router($publicId);
        $oldSnapshot = $this->snapshot($router);
        DB::transaction(function () use ($request, $router, $oldSnapshot): void {
            $router->delete();
            $this->auditLogger->record($request, 'router.deleted', $router, $oldSnapshot, ['result' => 'deleted']);
        });

        return response()->noContent();
    }

    public function connectionTest(Request $request, string $publicId): JsonResponse
    {
        $router = $this->router($publicId);

        try {
            $result = $this->routerManager->testConnection($router)->toArray();
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->record($request, 'router.connection_tested', $router, [], ['result' => $this->failedActionResult($router)]);

            return $this->unprocessable($exception->getMessage());
        }

        $this->auditLogger->record($request, 'router.connection_tested', $router, [], ['result' => $result]);

        return $this->actionResult($result);
    }

    public function systemInfo(Request $request, string $publicId): JsonResponse
    {
        $router = $this->router($publicId);

        try {
            $result = $this->routerManager->getSystemInfo($router)->toArray();
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->record($request, 'router.system_info_requested', $router, [], ['result' => $this->failedActionResult($router)]);

            return $this->unprocessable($exception->getMessage());
        }

        $this->auditLogger->record($request, 'router.system_info_requested', $router, [], ['result' => $result]);

        return $this->actionResult($result);
    }

    private function router(string $publicId): Router
    {
        return Router::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function driver(Router $router): \App\Contracts\RouterDriverInterface
    {
        try {
            return $this->routerManager->driverFor($router);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['driver' => [$exception->getMessage()]]);
        }
    }

    /** @param array<string, mixed> $result */
    private function actionResult(array $result): JsonResponse
    {
        if (($result['status'] ?? null) === 'unsupported') {
            return response()->json([
                'message' => $result['message'] ?? 'This router operation is not supported by the selected driver.',
                'data' => $result,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $result]);
    }

    private function unprocessable(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @return array<string, string> */
    private function failedActionResult(Router $router): array
    {
        return [
            'status' => 'failed',
            'driver' => $router->driver,
            'message' => 'Router driver action failed.',
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(Router $router): array
    {
        return $router->only([
            'public_id',
            'name',
            'hostname',
            'management_ip',
            'vendor',
            'model',
            'software_version',
            'serial_number',
            'driver',
            'preferred_transport',
            'status',
            'capabilities',
            'last_contact_at',
            'last_synchronized_at',
            'notes',
            'created_by',
        ]);
    }

    /** @return array<string, mixed> */
    private function resource(Router $router, mixed $credential = null): array
    {
        $credential ??= $router->primaryCredential()->first();
        $data = $router->toArray();
        $data['credential_configured'] = $credential !== null && $this->credentialService->isConfigured($router, $credential);
        $data['credential_profile'] = $credential ? $this->credentialService->metadata($credential) : null;
        $data['credential_version'] = $credential?->version;

        return $data;
    }

    /** @return array<string, mixed> */
    private function credentialSnapshot(mixed $credential): array
    {
        return $credential ? ['credential_profile' => $this->credentialService->metadata($credential)] : [];
    }
}
