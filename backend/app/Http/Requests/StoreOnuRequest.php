<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOnuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'vendor' => ['required', 'string', 'max:120'],
            'model' => ['required', 'string', 'max:190'],
            'serial_number' => ['nullable', 'string', 'max:190', 'unique:onus,serial_number'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'status' => ['sometimes', 'string', 'in:in_stock,reserved,assigned,faulty,retired'],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
