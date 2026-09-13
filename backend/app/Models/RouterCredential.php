<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterCredential extends Model
{
    use HasFactory, HasPublicId;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'auth_type',
        'is_primary',
        'version',
        'last_used_at',
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
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /** @return array<string, mixed> */
    public function toResourceArray(): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'auth_type' => $this->auth_type,
            'is_primary' => $this->is_primary,
            'version' => $this->version,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
