<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOltTerminalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:6', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:12', 'max:128'],
            'profile_name' => ['required', 'string', 'in:root,ispadmin', 'max:15'],
            'privilege_level' => ['required', 'integer', 'between:0,15'],
            'reenter_limit' => ['sometimes', 'integer', 'between:0,20'],
            'appended_info' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'string', 'in:draft,ready,applied,error'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
