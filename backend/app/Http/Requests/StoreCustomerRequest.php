<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        return [
            'customer_type' => ['required', 'string', 'in:residential,business,corporate'],
            'legal_name' => ['required', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'portal_username' => ['nullable', 'string', 'max:120'],
            'portal_password' => ['nullable', 'string', 'max:255'],
            'ppp_username' => ['nullable', 'string', 'max:120'],
            'ppp_password' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
