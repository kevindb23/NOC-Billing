<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOltVlanProvisionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    protected function prepareForValidation(): void
    {
        $this->merge(['vlan_type' => $this->input('vlan_type', 'smart')]);
    }

    public function rules(): array
    {
        return ['vlan_id' => ['required', 'integer', 'between:1,4094'], 'vlan_to' => ['nullable', 'integer', 'between:1,4094', 'required_if:vlan_type,to', 'gte:vlan_id'], 'vlan_type' => ['required', 'string', 'in:mux,smart,standard,super,to'], 'name' => ['required', 'string', 'max:190'], 'service_mode' => ['required', 'string', 'in:internet,tr069'], 'frame' => ['nullable', 'integer', 'in:0', 'required_if:service_mode,tr069'], 'slot' => ['nullable', 'integer', 'min:0', 'required_if:service_mode,tr069'], 'port_number' => ['nullable', 'integer', 'between:0,3', 'required_if:service_mode,tr069'], 'port' => ['nullable', 'string', 'max:20'], 'status' => ['sometimes', 'string', 'in:draft,ready,applied'], 'notes' => ['nullable', 'string', 'max:5000'], 'username' => ['nullable', 'string', 'max:190'], 'password' => ['nullable', 'string', 'max:190']];
    }
}
