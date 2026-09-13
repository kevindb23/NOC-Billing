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

        $credentialKey = '(?<![A-Za-z0-9_.-])(?:access[ \\t_-]?key|api[ \\t_-]?key|auth(?:orization|orisation)?|certificate|cert|client[ \\t_-]?secret|community|cookie|credential(?:s)?|key|known[ \\t_-]?hosts|login|passphrase|password|pem|private[ \\t_-]?key|private|secret|session|snmp|ssl|tls|token|user(?:[ \\t_-]?name)?|username)';

        preg_match_all(
            '/(?P<key>'.$credentialKey.')(?P<separator>[ \\t]*(?:=|:)[ \\t]*|[ \\t]+)/i',
            $value,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        if (empty($matches['key'])) {
            return $value;
        }

        $length = strlen($value);
        $spans = [];
        $matchCount = count($matches['key']);
        $coveredUntil = -1;

        preg_match_all(
            '/[A-Za-z][A-Za-z0-9_.-]*[ \\t]*(?:=|:)[ \\t]*/',
            $value,
            $assignmentMatches,
            PREG_OFFSET_CAPTURE,
        );

        for ($index = 0; $index < $matchCount; $index++) {
            $key = trim($matches['key'][$index][0]);

            if ($matches[0][$index][1] < $coveredUntil || ! self::isSensitiveKey($key)) {
                continue;
            }

            $matchStart = $matches[0][$index][1];
            $markerEnd = $matchStart + strlen($matches[0][$index][0]);
            $valueStart = $markerEnd;
            $valueEnd = $length;
            $nextMarkerStart = $length;

            foreach ($assignmentMatches[0] as $assignmentMatch) {
                if ($assignmentMatch[1] > $matchStart) {
                    $nextMarkerStart = $assignmentMatch[1];

                    break;
                }
            }

            if ($valueStart < $length && in_array($value[$valueStart], ['"', "'"], true)) {
                $quote = $value[$valueStart];
                $escaped = false;
                $valueEnd = $valueStart + 1;

                while ($valueEnd < $length) {
                    $character = $value[$valueEnd];

                    if ($character === $quote && ! $escaped) {
                        $valueEnd++;

                        break;
                    }

                    $escaped = $character === '\\' && ! $escaped;

                    if ($character !== '\\') {
                        $escaped = false;
                    }

                    $valueEnd++;
                }
            } else {
                $valueEnd = $nextMarkerStart;
                $remaining = substr($value, $valueStart, $valueEnd - $valueStart);

                foreach ([',', ';', "\\r", "\\n"] as $delimiter) {
                    $delimiterPosition = strpos($remaining, $delimiter);

                    if ($delimiterPosition !== false) {
                        $valueEnd = min($valueEnd, $valueStart + $delimiterPosition);
                    }
                }

                while ($valueEnd > $valueStart && ctype_space($value[$valueEnd - 1])) {
                    $valueEnd--;
                }
            }

            $spans[] = [
                'start' => $matchStart,
                'end' => $valueEnd,
                'replacement' => substr($value, $matchStart, $markerEnd - $matchStart).'[REDACTED]',
            ];
            $coveredUntil = $valueEnd;
        }

        for ($index = count($spans) - 1; $index >= 0; $index--) {
            $span = $spans[$index];
            $value = substr_replace($value, $span['replacement'], $span['start'], $span['end'] - $span['start']);
        }

        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = (string) preg_replace('/[^a-z0-9]/', '', strtolower($key));

        if ($normalized === 'credentialprofileid') {
            return false;
        }

        foreach ([
            'accesskey', 'apikey', 'auth', 'authorization', 'authorisation', 'certificate', 'cert',
            'community', 'credential', 'cookie', 'key', 'login', 'passphrase', 'password', 'pem',
            'private', 'secret', 'session', 'snmp', 'ssl', 'tls', 'token', 'user', 'username',
        ] as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
