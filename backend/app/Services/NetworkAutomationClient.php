<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NetworkAutomationClient
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function execute(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.network_automation.url'), '/');
        $token = (string) config('services.network_automation.token', '');

        if ($baseUrl === '' || $token === '') {
            throw new NetworkAutomationException(
                'The network automation service is not configured.',
                'automation_service_unavailable',
                503,
            );
        }

        $correlationId = is_string($payload['correlation_id'] ?? null)
            ? $payload['correlation_id']
            : null;

        try {
            $request = Http::asJson()
                ->acceptJson()
                ->withToken($token)
                ->connectTimeout((float) config('services.network_automation.connect_timeout', 5))
                ->timeout((float) config('services.network_automation.timeout', 60));

            $response = $request->post($baseUrl.'/operations', $payload);
        } catch (\Throwable) {
            throw new NetworkAutomationException(
                'The network automation service could not be reached.',
                'automation_service_unavailable',
                503,
            );
        }

        if (! $response->successful()) {
            $body = $response->json();
            $code = is_array($body) && is_string($body['error_code'] ?? null)
                ? $body['error_code']
                : 'automation_service_error';

            throw new NetworkAutomationException(
                $response->status() === 422
                    ? 'The network automation service rejected this operation.'
                    : 'The network automation service returned an error.',
                $this->safeErrorCode($code),
                $response->status() === 422 ? 422 : 503,
            );
        }

        $body = $response->json();
        $result = is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : $body;

        if (! is_array($result)) {
            throw new NetworkAutomationException(
                'The network automation service returned an invalid result.',
                'invalid_automation_result',
                503,
            );
        }

        return $this->validatedResult($result, $correlationId);
    }

    /** @param array<string, mixed> $result */
    private function validatedResult(array $result, ?string $expectedCorrelationId): array
    {
        foreach (['operation', 'status', 'driver', 'transport', 'correlation_id', 'message'] as $field) {
            if (! array_key_exists($field, $result) || ! is_string($result[$field]) || trim($result[$field]) === '') {
                throw new NetworkAutomationException(
                    'The network automation service returned an invalid result.',
                    'invalid_automation_result',
                    503,
                );
            }
        }

        if ($expectedCorrelationId !== null && $result['correlation_id'] !== $expectedCorrelationId) {
            throw new NetworkAutomationException(
                'The network automation service returned a mismatched correlation ID.',
                'invalid_automation_result',
                503,
            );
        }

        if (! in_array($result['status'], ['succeeded', 'connected', 'not_configured', 'unsupported', 'failed'], true)) {
            throw new NetworkAutomationException(
                'The network automation service returned an invalid result status.',
                'invalid_automation_result',
                503,
            );
        }

        return [
            'operation' => $result['operation'],
            'status' => $result['status'],
            'driver' => $result['driver'],
            'transport' => $result['transport'],
            'correlation_id' => $result['correlation_id'],
            'message' => $this->safeMessage($result['message']),
            'checked_at' => is_string($result['checked_at'] ?? null) ? $result['checked_at'] : now()->toIso8601String(),
            'details' => is_array($result['details'] ?? null) ? $result['details'] : null,
        ];
    }

    private function safeErrorCode(string $code): string
    {
        $code = strtolower(trim($code));

        return preg_match('/^[a-z0-9_\-]{1,80}$/', $code) === 1
            ? $code
            : 'automation_service_error';
    }

    private function safeMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'The network automation service returned no message.';
        }

        return preg_replace(
            '/(password|passphrase|token|community|private[ _-]?key|api[ _-]?key|authorization)\s*[:=]\s*([^,;\s]+)/i',
            '$1: [REDACTED]',
            mb_substr($message, 0, 2000),
        ) ?? 'The network automation service returned an invalid message.';
    }
}
