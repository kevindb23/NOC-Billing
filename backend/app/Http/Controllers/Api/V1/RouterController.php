<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouterRequest;
use App\Http\Requests\TestRouterConnectionRequest;
use App\Http\Requests\UpdateRouterRequest;
use App\Models\Router;
use App\Services\RouterCommandExecutor;
use App\Services\RouterSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class RouterController extends Controller
{
    public function connect(string $publicId, RouterSessionService $sessions): JsonResponse { try { $router=Router::query()->where('public_id',$publicId)->firstOrFail(); $router->update(['session_requested'=>true]); return response()->json(['data'=>$sessions->start($router)]); } catch (RuntimeException $e) { return response()->json(['message'=>$e->getMessage()],422); } }
    public function disconnect(string $publicId, RouterSessionService $sessions): JsonResponse { try { $router=Router::query()->where('public_id',$publicId)->firstOrFail(); $router->update(['session_requested'=>false]); return response()->json(['data'=>$sessions->stop($router)]); } catch (RuntimeException $e) { return response()->json(['message'=>$e->getMessage()],422); } }
    public function sessionStatus(string $publicId, RouterSessionService $sessions): JsonResponse { try { return response()->json(['data'=>$sessions->status(Router::query()->where('public_id',$publicId)->firstOrFail())]); } catch (RuntimeException $e) { return response()->json(['message'=>$e->getMessage()],422); } }
    public function testConnection(TestRouterConnectionRequest $request, RouterCommandExecutor $executor): JsonResponse
    {
        try {
            return response()->json(['data' => $executor->execute($request->validated())]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Router::query()
                ->latest()
                ->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function store(StoreRouterRequest $request): JsonResponse
    {
        $values = $this->credentialPayload($request->validated());
        $values['preferred_transport'] ??= 'ssh';

        return response()->json(['data' => Router::create($values)->fresh()], Response::HTTP_CREATED);
    }

    public function show(string $publicId): JsonResponse
    {
        $router = Router::query()->where('public_id', $publicId)->firstOrFail();
        $data = $router->toArray();
        $data['username'] = $router->ssh_username;
        $data['password'] = $router->ssh_password;

        return response()->json(['data' => $data]);
    }

    public function update(UpdateRouterRequest $request, string $publicId): JsonResponse
    {
        $router = Router::query()->where('public_id', $publicId)->firstOrFail();
        $router->update($this->credentialPayload($request->validated()));

        return response()->json(['data' => $router->fresh()]);
    }

    public function destroy(Request $request, string $publicId): Response
    {
        $router = Router::withTrashed()->where('public_id', $publicId)->firstOrFail();
        $request->boolean('permanent') ? $router->forceDelete() : $router->delete();

        return response()->noContent();
    }

    /** @param array<string, mixed> $values */
    private function credentialPayload(array $values): array
    {
        if (array_key_exists('username', $values)) {
            $values['ssh_username'] = $values['username'];
        }
        if (array_key_exists('password', $values)) {
            $values['ssh_password'] = $values['password'];
        }

        unset($values['username'], $values['password']);

        return $values;
    }
}
