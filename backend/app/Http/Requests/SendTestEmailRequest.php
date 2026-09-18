<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array { return ['recipient' => ['required', 'email', 'max:255']]; }
}
