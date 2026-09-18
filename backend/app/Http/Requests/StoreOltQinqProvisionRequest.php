<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOltQinqProvisionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return ['outer_vlan' => ['nullable', 'integer', 'between:1,4094', 'required_if:qinq_type,s_vlan', 'required_if:qinq_type,ont_line_profile'], 'inner_vlan' => ['nullable', 'integer', 'between:1,4094', 'required_if:qinq_type,c_vlan', 'required_if:qinq_type,ont_line_profile'], 'qinq_type' => ['sometimes', 'string', 'in:s_vlan,c_vlan,ont_line_profile'], 'service_port_id' => ['prohibited'], 'frame' => ['nullable', 'integer', 'in:0', 'required_if:qinq_type,s_vlan'], 'slot' => ['nullable', 'integer', 'min:0', 'required_if:qinq_type,s_vlan'], 'port_number' => ['nullable', 'integer', 'between:0,3', 'required_if:qinq_type,s_vlan'], 'ont_line_profile' => ['prohibited'], 'profile_id' => ['prohibited'], 'dba_profile_id' => ['nullable', 'integer', 'between:1,65535', 'required_if:qinq_type,ont_line_profile'], 'port' => ['nullable', 'string', 'max:20'], 'name' => ['nullable', 'string', 'max:190', 'required_unless:qinq_type,ont_line_profile'], 'status' => ['sometimes', 'string', 'in:draft,ready,applied'], 'notes' => ['nullable', 'string', 'max:5000'], 'username' => ['nullable', 'string', 'max:190'], 'password' => ['nullable', 'string', 'max:190']];
    }
}
