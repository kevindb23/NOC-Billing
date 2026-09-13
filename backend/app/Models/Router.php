<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Router extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const SAFE_METADATA_KEYS = ['site', 'location', 'rack', 'role', 'description', 'tags'];

    protected $hidden = ['metadata'];

    protected $fillable = [
        'name',
        'hostname',
        'management_ip',
        'vendor',
        'model',
        'software_version',
        'serial_number',
        'driver',
        'preferred_transport',
        'status',
        'capabilities',
        'last_contact_at',
        'last_synchronized_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'metadata' => 'array',
        'last_contact_at' => 'datetime',
        'last_synchronized_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(RouterCredential::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RouterOperation::class);
    }

    public static function isSafeMetadataKey(string $key): bool
    {
        return in_array($key, self::SAFE_METADATA_KEYS, true);
    }

    public function setMetadataAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['metadata'] = null;

            return;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Router metadata must be a flat object.');
        }

        foreach ($value as $key => $item) {
            if (! is_string($key) || ! self::isSafeMetadataKey($key) || (! is_scalar($item) && $item !== null)) {
                throw new InvalidArgumentException('Router metadata contains an unsupported or sensitive field.');
            }
        }

        $this->attributes['metadata'] = json_encode($value, JSON_THROW_ON_ERROR);
    }
}
