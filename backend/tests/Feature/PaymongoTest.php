<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class PaymongoTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSingleInstallationContext;

    public function test_authorized_user_can_save_and_read_sanitized_paymongo_settings(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);
        $request = $this;

        $request->putJson('/api/v1/paymongo', [
            'enabled' => true, 'environment' => 'test', 'public_key' => 'pk_test_123',
            'secret_key' => 'sk_test_secret', 'webhook_secret' => 'whsec_secret',
        ])->assertOk()->assertJsonPath('data.settings.enabled', true)->assertJsonMissingPath('data.settings.secret_key');

        $this->assertDatabaseHas('organization_paymongo_settings', ['user_id' => $user->id, 'public_key' => 'pk_test_123']);
        $settings = $user->paymongoSettings()->firstOrFail();
        $this->assertSame('sk_test_secret', Crypt::decryptString($settings->secret_key));
        $request->getJson('/api/v1/paymongo')->assertOk()->assertJsonPath('data.settings.secret_key_configured', true)->assertJsonMissingPath('data.settings.secret_key');
        $this->assertDatabaseHas('audit_logs', ['action' => 'paymongo.settings.updated']);
    }

    public function test_paymongo_test_requires_saved_configuration_and_audits(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);
        $request = $this;
        $request->postJson('/api/v1/paymongo/test')->assertStatus(422);
        $request->putJson('/api/v1/paymongo', ['enabled' => true, 'environment' => 'test', 'public_key' => 'pk_test_123', 'secret_key' => 'sk_test_secret', 'webhook_secret' => 'whsec_secret'])->assertOk();
        $request->postJson('/api/v1/paymongo/test')->assertOk()->assertJsonPath('data.message', 'Paymongo configuration is valid.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'paymongo.tested']);
    }

    public function test_test_environment_can_create_a_test_payment(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);
        Http::fake(['https://api.paymongo.com/*' => Http::response(['data' => ['id' => 'cs_test_123', 'attributes' => ['status' => 'active', 'checkout_url' => 'https://checkout.paymongo.com/cs_test_123']]], 200)]);
        $request = $this;
        $request->putJson('/api/v1/paymongo', ['enabled' => true, 'environment' => 'test', 'public_key' => 'pk_test_123', 'secret_key' => 'sk_test_secret'])->assertOk();
        $request->postJson('/api/v1/paymongo/test-payment', ['amount' => 10000, 'currency' => 'PHP', 'customer_email' => 'customer@example.com', 'description' => 'Test invoice'])->assertOk()->assertJsonPath('data.id', 'cs_test_123')->assertJsonPath('data.checkout_url', 'https://checkout.paymongo.com/cs_test_123');
        Http::assertSent(fn ($http) => $http->url() === 'https://api.paymongo.com/v1/checkout_sessions' && $http->data()['data']['attributes']['line_items'][0]['amount'] === 10000);
        $this->assertDatabaseHas('audit_logs', ['action' => 'paymongo.test_payment_created']);
    }

    private function authorizedContext(): User
    {
        return $this->installationUser(['paymongo.view', 'paymongo.update', 'paymongo.test']);
    }
}
