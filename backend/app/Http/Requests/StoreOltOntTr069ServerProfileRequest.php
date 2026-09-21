<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOltOntTr069ServerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'profile_id' => ['required', 'integer', 'between:1,32'],
            'profile_name' => ['required', 'string', 'max:190'],
            'url' => ['required', 'url', 'max:1000'],
            'username' => ['required', 'string', 'max:190'],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'max:4000'],
            'status' => ['sometimes', 'string', 'in:draft,ready,applied'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
