<?php

namespace App\Services;

use App\Models\Organization;

class OrganizationBrandingService
{
    public function resolved(Organization $organization): array
    {
        $defaults = [
            'organization_name' => $organization->name,
            'short_name' => $organization->name,
            'brand_mark' => null,
            'tagline' => 'Billing operations',
            'logo_url' => null,
            'primary_color' => null,
            'accent_color' => null,
        ];

        return array_merge($defaults, $organization->branding?->only(array_keys($defaults)) ?? []);
    }

    public function defaults(Organization $organization): array
    {
        return [
            'organization_name' => $organization->name,
            'short_name' => $organization->name,
            'brand_mark' => null,
            'tagline' => 'Billing operations',
            'logo_url' => null,
            'primary_color' => null,
            'accent_color' => null,
        ];
    }
}
