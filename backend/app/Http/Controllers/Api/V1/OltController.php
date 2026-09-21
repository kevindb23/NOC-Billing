<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOltRequest;
use App\Http\Requests\TestOltConnectionRequest;
use App\Http\Requests\UpdateOltRequest;
use App\Models\Olt;
use App\Models\OntSetting;
use App\Services\OltConnectionTester;
use App\Services\OltSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use RuntimeException;

class OltController extends Controller
{
    public function testConnection(TestOltConnectionRequest $request, OltConnectionTester $tester): JsonResponse
    {
        try {
            return response()->json(['data' => $tester->test($request->validated())]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function connect(string $publicId, OltSessionService $sessions): JsonResponse
    {
        try { $olt = Olt::query()->where('public_id', $publicId)->firstOrFail(); $olt->update(['session_requested' => true]); return response()->json(['data' => $sessions->start($olt)]); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    public function disconnect(string $publicId, OltSessionService $sessions): JsonResponse
    {
        try { $olt = Olt::query()->where('public_id', $publicId)->firstOrFail(); $olt->update(['session_requested' => false]); return response()->json(['data' => $sessions->stop($olt)]); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    public function sessionStatus(string $publicId, OltSessionService $sessions): JsonResponse
    {
        try { return response()->json(['data' => $sessions->status(Olt::query()->where('public_id', $publicId)->firstOrFail())]); }
        catch (RuntimeException $exception) { return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => Olt::query()->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function store(StoreOltRequest $request): JsonResponse
    {
        $values = $request->validated();
        $values['ssh_username'] = $values['username'] ?? null;
        $values['ssh_password'] = $values['password'] ?? null;
        unset($values['username'], $values['password']);
        $values['ont_id_capacity_per_port'] ??= (int) OntSetting::query()->firstOrCreate([], ['do_not_allow_rogue_onus' => false, 'ont_id_capacity_per_port' => 64])->ont_id_capacity_per_port;
        return response()->json(['data' => Olt::create($values)->fresh()], Response::HTTP_CREATED);
    }

    public function show(string $publicId): JsonResponse
    {
        return response()->json(['data' => Olt::query()->where('public_id', $publicId)->firstOrFail()]);
    }

    public function update(UpdateOltRequest $request, string $publicId): JsonResponse
    {
        $olt = Olt::query()->where('public_id', $publicId)->firstOrFail();
        $values = $request->validated();
        if (array_key_exists('username', $values)) $values['ssh_username'] = $values['username'];
        if (array_key_exists('password', $values) && filled($values['password'])) $values['ssh_password'] = $values['password'];
        unset($values['username'], $values['password']);
        $olt->update($values);

        return response()->json(['data' => $olt->fresh()]);
    }

    public function destroy(Request $request, string $publicId): Response
    {
        $olt = Olt::withTrashed()->where('public_id', $publicId)->firstOrFail();
        $request->boolean('permanent') ? $olt->forceDelete() : $olt->delete();

        return response()->noContent();
    }
}
