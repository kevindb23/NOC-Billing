<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOnuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $publicId = (string) $this->route('publicId');

        return [
            'vendor' => ['sometimes', 'string', 'max:120'],
            'model' => ['sometimes', 'string', 'max:190'],
            'serial_number' => ['nullable', 'string', 'max:190', Rule::unique('onus', 'serial_number')->ignore($publicId, 'public_id')],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'status' => ['sometimes', 'string', 'in:in_stock,reserved,assigned,faulty,retired'],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
