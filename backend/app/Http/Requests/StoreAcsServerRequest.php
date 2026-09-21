<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcsServerRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        $editing = $this->isMethod('PUT') || $this->isMethod('PATCH');
        return ['name' => ['required', 'string', 'max:190'], 'api_url' => ['required', 'url:http,https', 'max:1000'], 'api_username' => ['required', 'string', 'max:190'], 'api_password' => [$editing ? 'nullable' : 'required', 'string', 'max:1000'], 'transport' => ['required', 'in:cwmp'], 'status' => ['required', 'in:active,inactive'], 'ssh_username' => ['required', 'string', 'max:190'], 'ssh_password' => [$editing ? 'nullable' : 'required', 'string', 'max:1000'], 'ssh_port' => ['required', 'integer', 'between:1,65535'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
