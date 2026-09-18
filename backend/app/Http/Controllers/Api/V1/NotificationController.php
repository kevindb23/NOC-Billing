<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Models\OrganizationNotificationSetting;
use App\Services\AuditLogger;
use App\Services\OrganizationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class NotificationController extends Controller
{
    public function __construct(private OrganizationNotificationService $notifications, private AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => ['settings' => $this->notifications->resolved($request->user())]]);
    }

    public function update(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $settings = OrganizationNotificationSetting::firstOrNew(['user_id' => $user->id]);
        $old = $this->notifications->resolved($user);
        $values = $request->validated();
        if (blank($values['telegram_bot_token'] ?? null) && $settings->exists) unset($values['telegram_bot_token']);
        if (array_key_exists('telegram_bot_token', $values) && filled($values['telegram_bot_token'])) $values['telegram_bot_token'] = Crypt::encryptString($values['telegram_bot_token']);
        $settings->fill($values)->save();
        $new = $this->notifications->resolved($user);
        $this->auditLogger->record($request, 'notifications.settings.updated', $settings, $old, $new);
        return response()->json(['data' => ['settings' => $new]]);
    }

    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->notificationSettings()->first();
        abort_unless($settings?->telegram_enabled && filled($settings->telegram_bot_token) && filled($settings->telegram_chat_id), 422, 'Enable and save Telegram settings before testing.');
        $this->notifications->sendTelegram($user, 'Test notification from '.config('app.name').'.');
        $this->auditLogger->record($request, 'notifications.test_sent', $settings, [], ['channel' => 'telegram']);
        return response()->json(['data' => ['message' => 'Test notification sent.']]);
    }
}
