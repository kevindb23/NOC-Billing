<?php

namespace Tests\Unit;

use App\Services\NetworkAutomationClient;
use App\Services\NetworkAutomationException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NetworkAutomationClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.network_automation.url' => 'http://automation.test',
            'services.network_automation.token' => 'internal-service-token',
            'services.network_automation.connect_timeout' => 1,
            'services.network_automation.timeout' => 2,
        ]);
    }

    public function test_it_posts_a_private_gateway_request_and_validates_the_normalized_result(): void
    {
        Http::fake([
            'http://automation.test/operations' => Http::response([
                'operation' => 'get_system_info',
                'status' => 'succeeded',
                'driver' => 'juniper_router',
                'transport' => 'netconf',
                'correlation_id' => 'router-operation-1',
                'message' => 'get_system_info completed.',
                'checked_at' => '2026-09-13T12:00:00Z',
                'details' => ['data' => ['hostname' => 'mx-edge']],
            ]),
        ]);

        $result = app(NetworkAutomationClient::class)->execute([
            'operation' => 'get_system_info',
            'correlation_id' => 'router-operation-1',
            'credentials' => ['password' => 'never-persist-this'],
        ]);

        $this->assertSame('succeeded', $result['status']);
        $this->assertSame('router-operation-1', $result['correlation_id']);
        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer internal-service-token')
                && $request->url() === 'http://automation.test/operations'
                && $request['credentials']['password'] === 'never-persist-this';
        });
    }

    public function test_it_rejects_mismatched_or_malformed_gateway_results_without_exposing_payloads(): void
    {
        Http::fake([
            'http://automation.test/operations' => Http::response([
                'operation' => 'get_system_info',
                'status' => 'succeeded',
                'driver' => 'juniper_router',
                'transport' => 'netconf',
                'correlation_id' => 'different-correlation',
                'message' => 'password=super-secret',
            ]),
        ]);

        try {
            app(NetworkAutomationClient::class)->execute([
                'operation' => 'get_system_info',
                'correlation_id' => 'router-operation-2',
                'credentials' => ['password' => 'super-secret'],
            ]);
            $this->fail('Expected an invalid result exception.');
        } catch (NetworkAutomationException $exception) {
            $this->assertSame('invalid_automation_result', $exception->errorCode);
            $this->assertStringNotContainsString('super-secret', $exception->getMessage());
        }
    }

    public function test_gateway_errors_are_mapped_to_safe_service_errors(): void
    {
        Http::fake([
            'http://automation.test/operations' => Http::response([
                'error_code' => 'authentication_failed',
                'message' => 'password=super-secret',
            ], 422),
        ]);

        $this->expectException(NetworkAutomationException::class);
        try {
            app(NetworkAutomationClient::class)->execute([
                'operation' => 'test_connection',
                'correlation_id' => 'router-operation-3',
            ]);
        } catch (NetworkAutomationException $exception) {
            $this->assertSame(422, $exception->statusCode);
            $this->assertSame('authentication_failed', $exception->errorCode);
            $this->assertStringNotContainsString('super-secret', $exception->getMessage());
            throw $exception;
        }
    }
}
