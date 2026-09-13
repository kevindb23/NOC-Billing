<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterOperation extends Model
{
    use HasFactory, HasPublicId;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    protected $fillable = [
        'router_id',
        'requested_by',
        'operation',
        'driver',
        'transport',
        'parameters',
        'status',
        'result',
        'error_code',
        'error_message',
        'correlation_id',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'parameters' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function setParametersAttribute(mixed $value): void
    {
        $this->attributes['parameters'] = $this->asJson(self::redact($value));
    }

    public function setResultAttribute(mixed $value): void
    {
        $this->attributes['result'] = $this->asJson(self::redact($value));
    }

    public function setErrorMessageAttribute(?string $value): void
    {
        $this->attributes['error_message'] = $value === null ? null : self::redactString($value);
    }

    private static function redact(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::redactString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = self::redact($item);
        }

        return $redacted;
    }

    private static function redactString(string $value): string
    {
        if (preg_match('/-----BEGIN .*?(?:PRIVATE KEY|CERTIFICATE)-----/i', $value)) {
            return '[REDACTED]';
        }

        return (string) preg_replace(
            '/\\b(password|passphrase|token|community|secret|private[ _-]?key)\\b\\s*(?:[:=]\\s*)?[^\\s,.;]+/i',
            '$1 [REDACTED]',
            $value,
        );
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key));

        if ($normalized === 'credential_profile_id') {
            return false;
        }

        $words = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return (bool) array_intersect($words, [
            'api', 'auth', 'authorization', 'authorisation', 'certificate', 'certificates', 'cert',
            'community', 'credential', 'credentials', 'cookie', 'header', 'headers', 'key', 'keys',
            'passphrase', 'password', 'pem', 'private', 'secret', 'session', 'ssl', 'tls', 'token',
        ]);
    }
}
