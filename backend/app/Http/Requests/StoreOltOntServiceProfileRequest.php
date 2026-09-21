<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOltOntServiceProfileRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        return [
            'profile_id' => ['required', 'integer', 'between:0,8192'],
            'profile_name' => ['required', 'string', 'max:190'],
            'eth_port_count' => ['required', 'integer', 'between:1,4'],
            'port_modes' => ['required', 'array'],
            'port_modes.*' => ['required', 'string', 'in:transparent,qinq'],
            'status' => ['sometimes', 'string', 'in:draft,ready,applied'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
