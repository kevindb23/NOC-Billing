<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandingRequest;
use App\Models\OrganizationBranding;
use App\Services\AuditLogger;
use App\Services\OrganizationBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function __construct(private OrganizationBrandingService $branding, private AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'branding' => $this->branding->resolved(),
            'defaults' => $this->branding->defaults(),
        ]]);
    }

    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        $branding = OrganizationBranding::query()->firstOrNew();
        $oldValues = $this->branding->resolved();
        $branding->fill($request->validated());
        $branding->save();
        $newValues = $this->branding->resolved();

        $this->auditLogger->record($request, 'branding.updated', $branding, $oldValues, $newValues);

        return response()->json(['data' => [
            'branding' => $newValues,
            'defaults' => $this->branding->defaults(),
        ]]);
    }
}
