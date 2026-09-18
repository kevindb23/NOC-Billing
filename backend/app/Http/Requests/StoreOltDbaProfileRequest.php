<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOltDbaProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'profile_id' => ['nullable', 'integer', 'min:10', 'max:515', 'multiple_of:5'],
            'bandwidth_mbps' => ['required', 'integer', 'min:1', 'max:1000000'],
            'profile_name' => ['nullable', 'string', 'max:190'],
            'status' => ['sometimes', 'string', 'in:draft,ready,applied'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
