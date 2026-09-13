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

    private static function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/password|secret|token|credential|private[_-]?key|api[_-]?key|community/i', $key)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = self::redact($item);
        }

        return $redacted;
    }
}
