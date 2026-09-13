<?php

namespace App\Services;

use App\Models\OrganizationBranding;
use Illuminate\Support\Facades\Schema;

class OrganizationBrandingService
{
    public function resolved(): array
    {
        $defaults = [
            'organization_name' => config('app.name', 'ISP-in-a-Box'),
            'short_name' => config('app.name', 'ISP-in-a-Box'),
            'brand_mark' => null,
            'tagline' => 'Billing operations',
            'logo_url' => null,
            'primary_color' => null,
            'accent_color' => null,
        ];

        if (! Schema::hasTable('organization_brandings')) {
            return $defaults;
        }

        return array_merge($defaults, OrganizationBranding::query()->first()?->only(array_keys($defaults)) ?? []);
    }

    public function defaults(): array
    {
        return [
            'organization_name' => config('app.name', 'ISP-in-a-Box'),
            'short_name' => config('app.name', 'ISP-in-a-Box'),
            'brand_mark' => null,
            'tagline' => 'Billing operations',
            'logo_url' => null,
            'primary_color' => null,
            'accent_color' => null,
        ];
    }
}
