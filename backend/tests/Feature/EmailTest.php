<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSingleInstallationContext;

    public function test_authorized_user_can_read_and_update_email_settings_without_exposing_password(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/email', [
                'provider' => 'gmail',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'billing@example.com',
                'password' => 'app-password',
                'from_name' => 'ISP Billing',
                'from_email' => 'billing@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.settings.provider', 'gmail')
            ->assertJsonPath('data.settings.password_configured', true)
            ->assertJsonMissingPath('data.settings.password');

        $this->assertDatabaseHas('organization_email_settings', ['user_id' => $user->id, 'username' => 'billing@example.com']);
        $stored = $user->emailSettings()->firstOrFail()->password;
        $this->assertNotSame('app-password', $stored);
        $this->assertSame('app-password', Crypt::decryptString($stored));
        $this->assertDatabaseHas('audit_logs', ['action' => 'email.settings.updated']);
    }

    public function test_email_settings_are_user_scoped_and_test_email_is_audited(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);
        Mail::fake();

        $this->putJson('/api/v1/email', [
            'provider' => 'custom', 'host' => 'smtp.example.com', 'port' => 465, 'encryption' => 'ssl',
            'username' => 'billing@example.com', 'password' => 'app-password', 'from_name' => 'ISP Billing', 'from_email' => 'billing@example.com',
        ])->assertOk();

        $this->postJson('/api/v1/email/test', ['recipient' => 'test@example.com'])
            ->assertOk()
            ->assertJsonPath('data.message', 'Test email sent.');

        Mail::assertSent(\App\Mail\SmtpTestMail::class, fn ($mail) => $mail->hasTo('test@example.com'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'email.test_sent']);
    }

    public function test_email_settings_require_permissions(): void
    {
        $this->authorizedContext();
        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/email')->assertForbidden();
    }

    public function test_each_user_has_a_separate_email_profile(): void
    {
        $user = $this->authorizedContext();
        $secondUser = User::factory()->create();
        $role = Role::firstOrFail();
        $role->users()->attach($secondUser);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/email', [
            'provider' => 'custom', 'host' => 'smtp.one.example', 'port' => 587, 'encryption' => 'tls',
            'username' => 'one@example.com', 'password' => 'one-password', 'from_name' => 'One', 'from_email' => 'one@example.com',
        ])->assertOk();

        Sanctum::actingAs($secondUser);
        $this->putJson('/api/v1/email', [
            'provider' => 'custom', 'host' => 'smtp.two.example', 'port' => 465, 'encryption' => 'ssl',
            'username' => 'two@example.com', 'password' => 'two-password', 'from_name' => 'Two', 'from_email' => 'two@example.com',
        ])->assertOk();

        $this->assertDatabaseCount('organization_email_settings', 2);
    }

    private function authorizedContext(): User
    {
        return $this->installationUser(['email.view', 'email.update', 'email.test'], 'Email manager');
    }
}
