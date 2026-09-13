<?php

namespace App\DTO\Router;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class DeviceStatusResult
{
    public const STATUS_CONNECTED = 'connected';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_UNSUPPORTED = 'unsupported';

    public const STATUS_FAILED = 'failed';

    public readonly CarbonImmutable $checkedAt;

    /** @param array<string, mixed>|null $details */
    public function __construct(
        public readonly string $status,
        public readonly string $driver,
        public readonly ?string $vendor = null,
        public readonly ?string $hostname = null,
        public readonly ?string $model = null,
        public readonly ?string $serialNumber = null,
        public readonly ?string $softwareVersion = null,
        public readonly ?int $uptimeSeconds = null,
        public readonly string $message = '',
        CarbonInterface|string|null $checkedAt = null,
        ?array $details = null,
    ) {
        if (! in_array($status, self::statuses(), true)) {
            throw new InvalidArgumentException("Unsupported router device status [{$status}].");
        }

        $this->checkedAt = CarbonImmutable::parse($checkedAt ?? now());
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
            'vendor' => $this->vendor,
            'hostname' => $this->hostname,
            'model' => $this->model,
            'serial_number' => $this->serialNumber,
            'software_version' => $this->softwareVersion,
            'uptime_seconds' => $this->uptimeSeconds,
            'message' => $this->message,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'details' => $this->details,
        ];
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
            'passphrase', 'password', 'pem', 'private', 'secret', 'session', 'ssl', 'tls', 'token',
        ]);
    }

    private static function isCredentialContainer(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key));

        return in_array($normalized, ['certificate', 'certificates', 'headers', 'header', 'tls', 'ssl'], true);
    }
}
