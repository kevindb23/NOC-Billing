<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterCredential extends Model
{
    use HasFactory, HasPublicId;

    protected static function booted(): void
    {
        static::saving(function (self $credential): void {
            $credential->primary_router_key = $credential->is_primary
                ? $credential->router_id
                : null;
        });
    }

    /** @var list<string> */
    private const SAFE_CONNECTION_METADATA_FIELDS = ['port', 'api_base_url', 'auth_mode', 'snmp_version'];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'auth_type',
        'is_primary',
        'version',
        'last_used_at',
        'connection_metadata',
    ];

    /** @var list<string> */
    protected $hidden = [
        'id',
        'router_id',
        'username',
        'password',
        'private_key',
        'private_key_passphrase',
        'api_token',
        'snmp_community',
        'snmp_security',
        'known_hosts',
        'tls_ca_certificate',
        'tls_client_certificate',
        'tls_client_key',
        'primary_router_key',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'username' => 'encrypted',
        'password' => 'encrypted',
        'private_key' => 'encrypted',
        'private_key_passphrase' => 'encrypted',
        'api_token' => 'encrypted',
        'snmp_community' => 'encrypted',
        'snmp_security' => 'encrypted:array',
        'known_hosts' => 'encrypted',
        'tls_ca_certificate' => 'encrypted',
        'tls_client_certificate' => 'encrypted',
        'tls_client_key' => 'encrypted',
        'is_primary' => 'boolean',
        'version' => 'integer',
        'last_used_at' => 'datetime',
        'connection_metadata' => 'array',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /** @return array<string, mixed> */
    public function toResourceArray(): array
    {
        $connectionMetadata = is_array($this->connection_metadata) ? $this->connection_metadata : [];
        $connectionMetadata = array_intersect_key($connectionMetadata, array_flip(self::SAFE_CONNECTION_METADATA_FIELDS));

        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'auth_type' => $this->auth_type,
            'is_primary' => $this->is_primary,
            'version' => $this->version,
            'connection_metadata' => $connectionMetadata,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
