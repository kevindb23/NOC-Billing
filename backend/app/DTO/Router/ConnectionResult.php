<?php

namespace App\DTO\Router;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class ConnectionResult
{
    public const STATUS_CONNECTED = 'connected';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_UNSUPPORTED = 'unsupported';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public readonly array $capabilities;

    public readonly CarbonImmutable $checkedAt;

    /** @param array<string, mixed>|null $details */
    public function __construct(
        public readonly string $status,
        public readonly string $driver,
        array $capabilities,
        public readonly string $message,
        CarbonInterface|string $checkedAt,
        ?array $details = null,
    ) {
        $this->assertStatus($status);
        $this->capabilities = array_values(array_unique(array_map('strval', $capabilities)));
        $this->checkedAt = CarbonImmutable::parse($checkedAt);
        $this->details = self::redact($details);
    }

    /** @var array<string, mixed>|null */
    public readonly ?array $details;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'driver' => $this->driver,
            'capabilities' => $this->capabilities,
            'message' => $this->message,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'details' => $this->details,
        ];
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, self::statuses(), true)) {
            throw new InvalidArgumentException("Unsupported router connection status [{$status}].");
        }
    }

    /** @return list<string> */
    private static function statuses(): array
    {
        return [
            self::STATUS_CONNECTED,
            self::STATUS_NOT_CONFIGURED,
            self::STATUS_UNSUPPORTED,
            self::STATUS_FAILED,
        ];
    }

    private static function redact(mixed $value, bool $credentialContainer = false): mixed
    {
        if (is_string($value) && preg_match('/-----BEGIN .*?(?:PRIVATE KEY|CERTIFICATE)-----/i', $value)) {
            return '[REDACTED]';
        }

        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            if ($credentialContainer && ! is_array($item)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = is_array($item) && self::isCredentialContainer($key)
                    ? self::redact($item, true)
                    : '[REDACTED]';

                continue;
            }

            $redacted[$key] = self::redact($item, $credentialContainer);
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key));
        $words = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return (bool) array_intersect($words, [
            'api', 'auth', 'authorization', 'authorisation', 'certificate', 'certificates', 'cert',
            'community', 'credential', 'credentials', 'cookie', 'header', 'headers', 'key', 'keys',
            'passphrase', 'password', 'pem', 'private', 'secret', 'session', 'token',
        ]);
    }

    private static function isCredentialContainer(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key));

        return in_array($normalized, ['certificate', 'certificates', 'headers', 'header', 'tls', 'ssl'], true);
    }
}
