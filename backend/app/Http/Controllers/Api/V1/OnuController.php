<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOnuRequest;
use App\Http\Requests\UpdateOnuRequest;
use App\Models\Onu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Onu::query()->latest()->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function store(StoreOnuRequest $request): JsonResponse
    {
        return response()->json(['data' => Onu::create($request->validated())->fresh()], Response::HTTP_CREATED);
    }

    public function show(string $publicId): JsonResponse
    {
        return response()->json(['data' => Onu::query()->where('public_id', $publicId)->firstOrFail()]);
    }

    public function update(UpdateOnuRequest $request, string $publicId): JsonResponse
    {
        $onu = Onu::query()->where('public_id', $publicId)->firstOrFail();
        $onu->update($request->validated());

        return response()->json(['data' => $onu->fresh()]);
    }

    public function destroy(string $publicId): Response
    {
        Onu::query()->where('public_id', $publicId)->firstOrFail()->delete();

        return response()->noContent();
    }
}
