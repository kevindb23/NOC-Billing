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
        'credential_profile_id',
        'credential_version',
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
        'credential_version' => 'integer',
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
        $this->attributes['error_message'] = $value === null ? null : self::redactErrorMessage($value);
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

        $credentialKey = '(?<![A-Za-z0-9_.-])(?:access[ \\t_-]?key|api[ \\t_-]?key|auth(?:orization|orisation)?|certificate|cert|client[ \\t_-]?secret|community|cookie|credential(?:s)?|header(?:s)?|key|known[ \\t_-]?hosts|login|passphrase|password|pem|private[ \\t_-]?key|private|secret|session|snmp|ssl|tls|token|user(?:[ \\t_-]?name)?|username)(?![A-Za-z0-9_.-])';

        if (preg_match('/'.$credentialKey.'[\"\']?[ \\t]*(?:=|:)/i', $value)) {
            return '[REDACTED]';
        }

        return $value;
    }

    private static function redactErrorMessage(string $value): string
    {
        if (preg_match('/(?:access[ \t_-]?key|api[ \t_-]?key|auth(?:orization|orisation)?|certificate|cert|client[ \t_-]?secret|community|cookie|credential(?:s)?|header(?:s)?|key|known[ \t_-]?hosts|login|passphrase|password|pem|private[ \t_-]?key|private|secret|session|snmp|ssl|tls|token|user(?:[ \t_-]?name)?|username)(?=\s|[=:])/i', $value)) {
            return '[REDACTED]';
        }

        return self::redactString($value);
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = (string) preg_replace('/[^a-z0-9]/', '', strtolower($key));

        if ($normalized === 'credentialprofileid') {
            return false;
        }

        foreach ([
            'accesskey', 'apikey', 'auth', 'authorization', 'authorisation', 'certificate', 'cert',
            'community', 'credential', 'cookie', 'header', 'headers', 'key', 'login', 'passphrase', 'password', 'pem',
            'private', 'secret', 'session', 'snmp', 'ssl', 'tls', 'token', 'user', 'username',
        ] as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
