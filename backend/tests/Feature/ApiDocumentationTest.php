<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documentation_catalog_matches_live_api_routes_and_excludes_stale_routes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/docs')->assertOk();
        $response->assertJsonPath('data.modules.gcash', 'GCash');

        $endpoints = $response->json('data.endpoints');

        $expectedEndpoints = [
            'DELETE /api-tokens/{id}',
            'DELETE /billing-accounts/{publicId}',
            'DELETE /billing-statements/{id}',
            'DELETE /customers/{publicId}',
            'DELETE /plans/{id}',
            'DELETE /roles/{id}',
            'DELETE /routers/{publicId}',
            'DELETE /subscriptions/{id}',
            'DELETE /users/{publicId}',
            'GET /api-tokens',
            'GET /audit-logs',
            'GET /auth/me',
            'GET /billing-accounts',
            'GET /billing-settings',
            'GET /billing-statements',
            'GET /branding',
            'GET /customers',
            'GET /customers/{publicId}',
            'GET /docs',
            'GET /email',
            'GET /gcash',
            'GET /gcash/invoices',
            'GET /gcash/payments',
            'GET /gcash/payments/{id}/receipt',
            'GET /gcash/statements',
            'GET /invoices',
            'GET /notifications',
            'GET /payments',
            'GET /paymongo',
            'GET /permissions',
            'GET /plans',
            'GET /roles',
            'GET /roles/{id}',
            'GET /routers',
            'GET /routers/{publicId}',
            'GET /routers/{publicId}/system-info',
            'GET /subscriber-services',
            'GET /subscriptions',
            'GET /users',
            'GET /users/{publicId}',
            'POST /api-tokens',
            'POST /auth/login',
            'POST /auth/logout',
            'POST /billing-accounts',
            'POST /billing-statements',
            'POST /customers',
            'POST /email/test',
            'POST /gcash/payments',
            'POST /gcash/payments/{id}/review',
            'POST /invoices',
            'POST /notifications/test',
            'POST /payments',
            'POST /paymongo/test',
            'POST /paymongo/test-payment',
            'POST /plans',
            'POST /roles',
            'POST /routers',
            'POST /routers/{publicId}/connection-test',
            'POST /subscriber-services',
            'POST /subscriptions',
            'POST /users',
            'PUT /billing-settings',
            'PUT /branding',
            'PUT /customers/{publicId}',
            'PUT /email',
            'PUT /gcash',
            'PUT /notifications',
            'PUT /paymongo',
            'PUT /plans/{id}',
            'PUT /roles/{id}',
            'PUT /routers/{publicId}',
            'PUT /subscriptions/{id}',
            'PUT /users/{publicId}',
        ];

        sort($endpoints);
        sort($expectedEndpoints);

        $this->assertSame($expectedEndpoints, $endpoints);
        $this->assertContains('GET /email', $endpoints);
        $this->assertContains('PUT /email', $endpoints);
        $this->assertContains('POST /email/test', $endpoints);
        $this->assertContains('GET /notifications', $endpoints);
        $this->assertContains('PUT /notifications', $endpoints);
        $this->assertContains('POST /notifications/test', $endpoints);
        $this->assertContains('GET /paymongo', $endpoints);
        $this->assertContains('PUT /paymongo', $endpoints);
        $this->assertContains('POST /paymongo/test', $endpoints);
        $this->assertContains('POST /paymongo/test-payment', $endpoints);
        $this->assertNotContains('GET|PUT|POST /email', $endpoints);
        $this->assertNotContains('GET|PUT|POST /notifications', $endpoints);
        $this->assertNotContains('GET|PUT|POST /paymongo', $endpoints);
        $this->assertNotContains('POST /email', $endpoints);
        $this->assertNotContains('POST /notifications', $endpoints);
        $this->assertNotContains('POST /paymongo', $endpoints);
    }
}
