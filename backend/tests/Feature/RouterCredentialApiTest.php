<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Router;
use App\Models\RouterCredential;
use App\Models\User;
use App\Services\RouterCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouterCredentialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ssh_requires_username_and_password_on_create(): void
    {
        $this->authenticateRouterManager();

        $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => ['name' => 'primary'],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_profile.username', 'credential_profile.password']);

        $this->assertDatabaseCount('routers', 0);
    }

    public function test_netconf_requires_username_and_password_on_create(): void
    {
        $this->authenticateRouterManager();

        $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'netconf',
            'credential_profile' => ['name' => 'primary'],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_profile.username', 'credential_profile.password']);

        $this->assertDatabaseCount('routers', 0);
    }

    public function test_api_token_must_be_a_non_empty_string_when_supplied(): void
    {
        $this->authenticateRouterManager();

        $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'api',
            'credential_profile' => ['name' => 'primary', 'api_token' => ['not-a-token']],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_profile.api_token']);

        $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'api',
            'credential_profile' => ['name' => 'primary', 'api_token' => ''],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_profile.api_token']);
    }

    public function test_snmp_requires_a_non_empty_community_when_profile_is_supplied(): void
    {
        $this->authenticateRouterManager();

        $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'snmp',
            'credential_profile' => ['name' => 'primary', 'snmp_community' => ''],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_profile.snmp_community']);

        $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'snmp',
            'credential_profile' => ['name' => 'primary', 'snmp_community' => ['private']],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_profile.snmp_community']);
    }

    public function test_transport_ports_must_be_valid(): void
    {
        $this->authenticateRouterManager();

        $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => [
                'name' => 'primary',
                'username' => 'netadmin',
                'password' => 'secret',
                'port' => 65536,
            ],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_profile.port']);
    }

    public function test_create_returns_only_secret_free_credential_metadata(): void
    {
        $this->authenticateRouterManager();

        $response = $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => [
                'name' => 'primary',
                'username' => 'netadmin',
                'password' => 'secret',
                'port' => 22,
            ],
        ]))->assertCreated()
            ->assertJsonPath('data.credential_configured', true)
            ->assertJsonPath('data.credential_profile.name', 'primary')
            ->assertJsonPath('data.credential_profile.auth_type', 'password')
            ->assertJsonPath('data.credential_profile.version', 1)
            ->assertJsonPath('data.credential_version', 1)
            ->assertJsonPath('data.credential_profile.connection_metadata.port', 22)
            ->assertJsonMissingPath('data.credential_profile.password');

        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertStringNotContainsString('netadmin', $response->getContent());
        $this->assertDatabaseCount('router_credentials', 1);
        $rawPassword = DB::table('router_credentials')->value('password');
        $this->assertNotSame('secret', $rawPassword);
    }

    public function test_index_and_show_return_safe_credential_metadata_without_n_plus_one_queries(): void
    {
        $this->authenticateRouterManager();
        $first = Router::create($this->routerPayload(['name' => 'First credential router']));
        $second = Router::create($this->routerPayload(['name' => 'Second credential router']));
        app(RouterCredentialService::class)->storeOrReplace($first, [
            'name' => 'primary',
            'api_base_url' => 'https://router-one.example.test/api',
            'auth_mode' => 'token',
            'api_token' => 'first-secret',
            'port' => 8443,
        ]);
        app(RouterCredentialService::class)->storeOrReplace($second, [
            'name' => 'primary',
            'api_token' => 'second-secret',
        ]);

        $credentialQueries = [];
        DB::listen(function ($query) use (&$credentialQueries): void {
            if (str_contains(strtolower($query->sql), 'router_credentials')) {
                $credentialQueries[] = $query->sql;
            }
        });

        $index = $this->getJson('/api/v1/routers?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.data.0.credential_configured', true)
            ->assertJsonPath('data.data.0.credential_profile.connection_metadata.port', 8443)
            ->assertJsonPath('data.data.0.credential_profile.connection_metadata.api_base_url', 'https://router-one.example.test/api')
            ->assertJsonPath('data.data.0.credential_profile.connection_metadata.auth_mode', 'token')
            ->assertJsonMissingPath('data.data.0.credential_profile.api_token');

        $this->assertCount(1, $credentialQueries);
        $this->assertStringNotContainsString('first-secret', $index->getContent());

        $this->getJson('/api/v1/routers/'.$first->public_id)
            ->assertOk()
            ->assertJsonPath('data.credential_configured', true)
            ->assertJsonPath('data.credential_profile.connection_metadata.port', 8443)
            ->assertJsonPath('data.credential_version', 1)
            ->assertJsonMissingPath('data.credential_profile.api_token');
    }

    public function test_update_without_replacing_blank_secrets_preserves_the_existing_profile(): void
    {
        $this->authenticateRouterManager();
        $created = $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => ['name' => 'primary', 'username' => 'netadmin', 'password' => 'secret'],
        ]))->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->putJson('/api/v1/routers/'.$publicId, [
            'name' => 'Updated router',
            'credential_profile' => ['name' => 'primary', 'username' => '', 'password' => ''],
        ])->assertOk()
            ->assertJsonPath('data.credential_configured', true)
            ->assertJsonPath('data.credential_version', 1);

        $credential = RouterCredential::query()->firstOrFail();
        $this->assertSame('netadmin', $credential->username);
        $this->assertSame('secret', $credential->password);
        $this->assertSame(1, $credential->version);
    }

    public function test_update_with_explicit_transport_allows_blank_secrets_to_preserve_the_existing_profile(): void
    {
        $this->authenticateRouterManager();
        $created = $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => ['name' => 'primary', 'username' => 'netadmin', 'password' => 'secret'],
        ]))->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->putJson('/api/v1/routers/'.$publicId, [
            'preferred_transport' => 'ssh',
            'credential_profile' => ['name' => 'primary', 'username' => '', 'password' => ''],
        ])->assertOk()
            ->assertJsonPath('data.credential_version', 1);

        $credential = RouterCredential::query()->firstOrFail();
        $this->assertSame('netadmin', $credential->username);
        $this->assertSame('secret', $credential->password);
    }

    public function test_secret_changes_increment_the_credential_version(): void
    {
        $this->authenticateRouterManager();
        $created = $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => ['name' => 'primary', 'username' => 'netadmin', 'password' => 'secret'],
        ]))->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->putJson('/api/v1/routers/'.$publicId, [
            'credential_profile' => ['name' => 'primary', 'password' => 'new-secret'],
        ])->assertOk()
            ->assertJsonPath('data.credential_version', 2)
            ->assertJsonPath('data.credential_profile.version', 2);

        $credential = RouterCredential::query()->firstOrFail();
        $this->assertSame('new-secret', $credential->password);
        $this->assertSame(2, $credential->version);
    }

    public function test_transport_changes_replace_connection_metadata_for_the_new_transport(): void
    {
        $this->authenticateRouterManager();
        $created = $this->postJson('/api/v1/routers', $this->routerPayload([
            'credential_profile' => [
                'name' => 'primary',
                'api_base_url' => 'https://router.example.test/api',
                'auth_mode' => 'token',
                'api_token' => 'api-secret',
                'port' => 8443,
            ],
        ]))->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->putJson('/api/v1/routers/'.$publicId, [
            'preferred_transport' => 'snmp',
            'credential_profile' => [
                'name' => 'primary',
                'snmp_version' => 'v3',
                'snmp_community' => 'snmp-secret',
            ],
        ])->assertOk()
            ->assertJsonPath('data.credential_profile.connection_metadata.port', 161)
            ->assertJsonPath('data.credential_profile.connection_metadata.snmp_version', 'v3')
            ->assertJsonMissingPath('data.credential_profile.connection_metadata.api_base_url')
            ->assertJsonMissingPath('data.credential_profile.connection_metadata.auth_mode');

        $this->putJson('/api/v1/routers/'.$publicId, [
            'preferred_transport' => 'ssh',
            'credential_profile' => [
                'name' => 'primary',
                'username' => 'ssh-user',
                'password' => 'ssh-secret',
            ],
        ])->assertOk()
            ->assertJsonPath('data.credential_profile.connection_metadata.port', 22)
            ->assertJsonMissingPath('data.credential_profile.connection_metadata.snmp_version');
    }

    public function test_transport_change_requires_credentials_for_the_new_transport(): void
    {
        $this->authenticateRouterManager();
        $created = $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => ['name' => 'primary', 'username' => 'netadmin', 'password' => 'secret'],
        ]))->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->putJson('/api/v1/routers/'.$publicId, ['preferred_transport' => 'netconf'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_profile.username', 'credential_profile.password']);

        $this->assertSame('ssh', Router::query()->where('public_id', $publicId)->value('preferred_transport'));
    }

    public function test_mock_transport_is_rejected_on_create_and_update(): void
    {
        $this->authenticateRouterManager();

        $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'mock',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['preferred_transport']);

        $router = Router::create($this->routerPayload(['name' => 'Legacy router', 'preferred_transport' => 'api']));
        $this->putJson('/api/v1/routers/'.$router->public_id, ['preferred_transport' => 'mock'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preferred_transport']);
    }

    public function test_audit_snapshots_contain_only_non_secret_credential_metadata(): void
    {
        $actor = $this->authenticateRouterManager();

        $this->withHeader('X-Request-Id', 'credential-audit-correlation')->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => ['name' => 'primary', 'username' => 'netadmin', 'password' => 'secret'],
        ]))->assertCreated();

        $log = AuditLog::query()->where('action', 'router.created')->firstOrFail();
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame('primary', $log->new_values['credential_profile']['name']);
        $this->assertSame(1, $log->new_values['credential_profile']['version']);
        $encoded = json_encode($log->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('netadmin', $encoded);
    }

    public function test_update_audit_snapshot_contains_old_safe_credential_metadata_and_version(): void
    {
        $this->authenticateRouterManager();
        $created = $this->postJson('/api/v1/routers', $this->routerPayload([
            'preferred_transport' => 'ssh',
            'credential_profile' => [
                'name' => 'primary',
                'username' => 'netadmin',
                'password' => 'old-secret',
                'port' => 2222,
            ],
        ]))->assertCreated();
        $publicId = $created->json('data.public_id');

        $this->putJson('/api/v1/routers/'.$publicId, [
            'credential_profile' => ['name' => 'primary', 'password' => 'new-secret'],
        ])->assertOk();

        $audit = AuditLog::query()->where('action', 'router.updated')->latest('id')->firstOrFail();
        $this->assertSame('primary', $audit->old_values['credential_profile']['name']);
        $this->assertSame(2222, $audit->old_values['credential_profile']['connection_metadata']['port']);
        $this->assertSame(1, $audit->old_values['credential_version']);
        $this->assertSame(2, $audit->new_values['credential_version']);
        $encoded = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('old-secret', $encoded);
        $this->assertStringNotContainsString('new-secret', $encoded);
        $this->assertStringNotContainsString('netadmin', $encoded);
    }

    public function test_transport_default_migration_reverses_mock_conversion_on_rollback(): void
    {
        $this->authenticateRouterManager();
        $router = Router::create($this->routerPayload([
            'name' => 'Legacy migration router',
            'preferred_transport' => 'mock',
        ]));
        $migration = require database_path('migrations/2026_09_13_000024_change_router_transport_default.php');

        $migration->up();
        $this->assertSame('api', $router->fresh()->preferred_transport);

        $migration->down();
        $this->assertSame('mock', $router->fresh()->preferred_transport);
    }

    private function authenticateRouterManager(): User
    {
        config(['auth.guards.sanctum' => ['driver' => 'session', 'provider' => 'users']]);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        $actor = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Router credential organization '.$actor->id,
            'slug' => 'router-credential-'.$actor->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'default_currency' => 'PHP',
        ]);
        $organization->users()->attach($actor, ['is_default' => true, 'status' => 'active']);
        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Router credential manager '.$actor->id,
            'guard_name' => 'api',
        ]);
        $permissions = collect(['routers.view', 'routers.create', 'routers.update'])
            ->map(fn (string $name): Permission => Permission::create(['name' => $name, 'guard_name' => 'api']));
        $role->permissions()->sync($permissions->pluck('id'));
        $role->users()->attach($actor, ['organization_id' => $organization->id]);
        Sanctum::actingAs($actor);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function routerPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Credential router',
            'vendor' => 'mikrotik',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'api',
            'status' => 'unknown',
        ], $overrides);
    }
}
