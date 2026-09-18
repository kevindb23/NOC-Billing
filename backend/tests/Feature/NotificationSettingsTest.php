<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSingleInstallationContext;

    public function test_authorized_user_can_save_notification_settings_without_exposing_token(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/notifications', [
            'telegram_enabled' => true, 'telegram_bot_token' => '123:secret-token', 'telegram_chat_id' => '-1001234567890',
        ])->assertOk()->assertJsonPath('data.settings.telegram_enabled', true)->assertJsonPath('data.settings.token_configured', true)->assertJsonMissingPath('data.settings.telegram_bot_token');

        $this->assertDatabaseHas('organization_notification_settings', ['user_id' => $user->id, 'telegram_chat_id' => '-1001234567890']);
        $this->assertSame('123:secret-token', Crypt::decryptString($user->notificationSettings()->firstOrFail()->telegram_bot_token));
        $this->assertDatabaseHas('audit_logs', ['action' => 'notifications.settings.updated']);
    }

    public function test_user_can_send_a_real_telegram_test_notification(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->putJson('/api/v1/notifications', [
            'telegram_enabled' => true, 'telegram_bot_token' => '123:secret-token', 'telegram_chat_id' => '-1001234567890',
        ]);
        $this->postJson('/api/v1/notifications/test')->assertOk()->assertJsonPath('data.message', 'Test notification sent.');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/bot123:secret-token/sendMessage') && $request['chat_id'] === '-1001234567890');
        $this->assertDatabaseHas('audit_logs', ['action' => 'notifications.test_sent']);
    }

    public function test_each_user_has_separate_notification_settings(): void
    {
        $user = $this->authorizedContext();
        $secondUser = User::factory()->create();
        $role = Role::firstOrFail();
        $role->users()->attach($secondUser);
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/notifications', ['telegram_enabled' => false, 'telegram_bot_token' => 'one', 'telegram_chat_id' => 'one-chat'])->assertOk();
        Sanctum::actingAs($secondUser);
        $this->putJson('/api/v1/notifications', ['telegram_enabled' => true, 'telegram_bot_token' => 'two', 'telegram_chat_id' => 'two-chat'])->assertOk();
        $this->assertDatabaseCount('organization_notification_settings', 2);
    }

    private function authorizedContext(): User
    {
        return $this->installationUser(['notifications.view', 'notifications.update', 'notifications.test'], 'Notification manager');
    }
}
