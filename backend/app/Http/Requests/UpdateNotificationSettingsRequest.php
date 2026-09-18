<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'telegram_enabled' => ['required', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:512'],
            'telegram_chat_id' => ['required', 'string', 'max:100'],
        ];
    }
}
