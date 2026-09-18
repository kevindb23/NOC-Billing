<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class OrganizationNotificationService
{
    public function resolved(User $user): array
    {
        $settings = $user->notificationSettings()->first();
        return [
            'telegram_enabled' => (bool) ($settings?->telegram_enabled ?? false),
            'token_configured' => filled($settings?->telegram_bot_token),
            'telegram_chat_id' => $settings?->telegram_chat_id ?? '',
        ];
    }

    public function sendTelegram(User $user, string $message): void
    {
        $settings = $user->notificationSettings()->firstOrFail();
        $token = Crypt::decryptString($settings->telegram_bot_token);
        $response = Http::asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $settings->telegram_chat_id, 'text' => $message]);
        $response->throw();
    }
}
