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
        $organization = $request->attributes->get('organization');

        return response()->json(['data' => [
            'branding' => $this->branding->resolved($organization),
            'defaults' => $this->branding->defaults($organization),
        ]]);
    }

    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        $organization = $request->attributes->get('organization');
        $branding = OrganizationBranding::firstOrNew(['organization_id' => $organization->id]);
        $oldValues = $this->branding->resolved($organization);
        $branding->fill($request->validated());
        $branding->save();
        $organization->unsetRelation('branding');
        $newValues = $this->branding->resolved($organization);

        $this->auditLogger->record($request, 'branding.updated', $branding, $oldValues, $newValues);

        return response()->json(['data' => [
            'branding' => $newValues,
            'defaults' => $this->branding->defaults($organization),
        ]]);
    }
}
