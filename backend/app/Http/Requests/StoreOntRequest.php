<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOntRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'olt_public_id' => ['required', 'string', 'exists:olts,public_id'],
            'acs_server_public_id' => ['nullable', 'string', 'exists:acs_servers,public_id'],
            'frame' => ['required', 'integer', 'between:0,255'],
            'slot' => ['required', 'integer', 'between:0,255'],
            'pon_port' => ['required', 'integer', 'between:0,255'],
            'ont_id' => ['nullable', 'integer', 'between:0,255'],
            'serial_number' => ['required', 'string', 'max:190'],
            'name' => ['required', 'string', 'max:190'],
            'status' => ['sometimes', 'string', 'in:unknown,discovered,rogue,online,offline'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
