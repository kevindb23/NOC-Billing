<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouterRequest;
use App\Http\Requests\UpdateRouterRequest;
use App\Models\Router;
use App\Services\RouterManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RouterController extends Controller
{
    public function __construct(private readonly RouterManager $routerManager) {}

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
        $router = new Router($data);
        $router->created_by = $request->user()->getAuthIdentifier();
        $router->capabilities = $this->driver($router)->capabilities();
        $router->save();

        return response()->json([
            'data' => $router->fresh(),
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
        $data = $request->validated();
        $router->fill($data);
        $router->capabilities = $this->driver($router)->capabilities();
        $router->save();

        return response()->json(['data' => $router->fresh()]);
    }

    public function destroy(string $publicId): Response
    {
        $this->router($publicId)->delete();

        return response()->noContent();
    }

    public function connectionTest(string $publicId): JsonResponse
    {
        $router = $this->router($publicId);

        try {
            $result = $this->routerManager->testConnection($router)->toArray();
        } catch (InvalidArgumentException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        return $this->actionResult($result);
    }

    public function systemInfo(string $publicId): JsonResponse
    {
        $router = $this->router($publicId);

        try {
            $result = $this->routerManager->getSystemInfo($router)->toArray();
        } catch (InvalidArgumentException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

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
}
