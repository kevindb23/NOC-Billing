<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymongoTestPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:100', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3', 'in:PHP'],
            'customer_email' => ['required', 'email', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
