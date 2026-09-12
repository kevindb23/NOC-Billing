<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'brand_mark' => ['sometimes', 'nullable', 'string', 'max:12'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'logo_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'primary_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
