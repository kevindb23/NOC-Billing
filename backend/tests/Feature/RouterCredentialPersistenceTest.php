<?php

namespace Tests\Feature;

use App\Models\Router;
use App\Models\RouterCredential;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RouterCredentialPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credential_secrets_are_encrypted_and_can_be_read_internally(): void
    {
        $router = Router::create([
            'name' => 'Credential persistence router',
            'driver' => 'cisco_router',
        ]);

        $credential = new RouterCredential([
            'name' => 'Primary device profile',
            'auth_type' => 'mixed',
            'is_primary' => true,
            'version' => 3,
            'last_used_at' => now(),
        ]);
        $credential->username = 'automation-user';
        $credential->password = 'router-password-value';
        $credential->private_key = '-----BEGIN PRIVATE KEY-----private-key-value';
        $credential->private_key_passphrase = 'private-key-passphrase';
        $credential->api_token = 'api-token-value';
        $credential->snmp_community = 'snmp-community-value';
        $credential->snmp_security = ['auth_key' => 'auth-key-value', 'privacy_key' => 'privacy-key-value'];
        $credential->known_hosts = 'router.example.test ssh-ed25519 AAAA';
        $credential->tls_ca_certificate = '-----BEGIN CERTIFICATE-----ca-value';
        $credential->tls_client_certificate = '-----BEGIN CERTIFICATE-----client-value';
        $credential->tls_client_key = '-----BEGIN PRIVATE KEY-----client-key-value';
        $credential->router()->associate($router);
        $credential->save();

        $raw = DB::table('router_credentials')->where('id', $credential->id)->first();
        $this->assertNotSame('router-password-value', $raw->password);
        $this->assertNotSame('api-token-value', $raw->api_token);
        $this->assertNotSame(json_encode(['auth_key' => 'auth-key-value', 'privacy_key' => 'privacy-key-value']), $raw->snmp_security);

        $fresh = $credential->fresh();
        $this->assertSame('automation-user', $fresh->username);
        $this->assertSame('router-password-value', $fresh->password);
        $this->assertSame(['auth_key' => 'auth-key-value', 'privacy_key' => 'privacy-key-value'], $fresh->snmp_security);
        $this->assertSame($router->id, $fresh->router->id);
    }

    public function test_credential_secrets_are_hidden_from_array_and_json_serialization(): void
    {
        $router = Router::create([
            'name' => 'Credential serialization router',
            'driver' => 'juniper_router',
        ]);

        $credential = new RouterCredential([
            'name' => 'Primary device profile',
            'auth_type' => 'password',
            'is_primary' => true,
        ]);
        $credential->username = 'automation-user';
        $credential->password = 'router-password-value';
        $credential->snmp_community = 'snmp-community-value';
        $credential->router()->associate($router);
        $credential->save();

        $array = $credential->fresh()->toArray();
        $json = $credential->fresh()->toJson();

        $this->assertSame($credential->public_id, $array['public_id']);
        $this->assertSame('Primary device profile', $array['name']);
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('username', $array);
        $this->assertArrayNotHasKey('snmp_community', $array);
        $this->assertStringNotContainsString('router-password-value', $json);
        $this->assertStringNotContainsString('snmp-community-value', $json);
    }

    public function test_force_deleting_a_router_cascades_credential_profiles(): void
    {
        $router = Router::create([
            'name' => 'Credential cascade router',
            'driver' => 'mikrotik_router',
        ]);

        $credential = new RouterCredential([
            'name' => 'Primary device profile',
            'auth_type' => 'token',
            'is_primary' => true,
        ]);
        $credential->api_token = 'api-token-value';
        $credential->router()->associate($router);
        $credential->save();

        $router->forceDelete();

        $this->assertDatabaseMissing('router_credentials', ['id' => $credential->id]);
    }

    public function test_router_allows_only_one_primary_credential_profile(): void
    {
        $router = Router::create([
            'name' => 'Primary uniqueness router',
            'driver' => 'cisco_router',
        ]);

        $first = new RouterCredential([
            'name' => 'First primary profile',
            'auth_type' => 'password',
            'is_primary' => true,
        ]);
        $first->router()->associate($router);
        $first->save();

        $this->expectException(QueryException::class);

        $second = new RouterCredential([
            'name' => 'Second primary profile',
            'auth_type' => 'password',
            'is_primary' => true,
        ]);
        $second->router()->associate($router);
        $second->save();
    }
}
