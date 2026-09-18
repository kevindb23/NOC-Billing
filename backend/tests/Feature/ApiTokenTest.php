<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSingleInstallationContext;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSingleInstallationContext;

    public function test_user_can_generate_a_scoped_token_once(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/api-tokens', [
            'name' => 'Billing integration', 'abilities' => ['customers.view', 'invoices.view'], 'expires_at' => now()->addMonth()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['token', 'token_record' => ['id', 'name', 'abilities', 'expires_at']]])->assertJsonPath('data.token_record.name', 'Billing integration');
        $plainToken = $response->json('data.token');
        $record = PersonalAccessToken::query()->where('name', 'Billing integration')->firstOrFail();
        $this->assertSame($user->id, $record->tokenable_id);
        $this->assertNotEmpty($plainToken);
        $this->assertDatabaseHas('audit_logs', ['action' => 'api_token.created']);
        $response->assertJsonMissing(['token' => $record->token]);
    }

    public function test_token_abilities_are_enforced_and_revocation_is_audited(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);
        $created = $this->postJson('/api/v1/api-tokens', ['name' => 'Read token', 'abilities' => ['customers.view']])->assertCreated();
        $token = $created->json('data.token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/customers')->assertOk();
        $this->withToken($token)->getJson('/api/v1/invoices')->assertForbidden();

        $recordId = $created->json('data.token_record.id');
        Sanctum::actingAs($user);
        $this->deleteJson("/api/v1/api-tokens/{$recordId}")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'api_token.revoked']);
    }

    public function test_token_list_and_docs_are_installation_scoped_and_include_all_modules(): void
    {
        $user = $this->authorizedContext();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/api-tokens', ['name' => 'Docs token', 'abilities' => ['*']])->assertCreated();
        $this->getJson('/api/v1/api-tokens')->assertOk()->assertJsonPath('data.0.name', 'Docs token');
        $this->getJson('/api/v1/docs')->assertOk()->assertJsonPath('data.modules.billing', 'Billing')->assertJsonPath('data.modules.notifications', 'Notifications')->assertJsonPath('data.modules.audit_logs', 'Audit logs');
    }

    private function authorizedContext(): User
    {
        return $this->installationUser(['api-tokens.view', 'api-tokens.create', 'api-tokens.delete', 'customers.view', 'invoices.view'], 'API manager');
    }
}
