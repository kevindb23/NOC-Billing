<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BngRadiusServer extends Model
{
    use HasPublicId;

    protected $table = 'bng_radius_servers';
    protected $fillable = ['bng_id', 'name', 'server_address', 'secret', 'database_name', 'database_username', 'database_password', 'sync_subscribers', 'auth_port', 'accounting_port', 'status', 'notes'];
    protected $hidden = ['secret', 'database_password'];

    protected $casts = [
        'secret' => 'encrypted',
        'database_password' => 'encrypted',
        'sync_subscribers' => 'boolean',
    ];

    public function bng(): BelongsTo
    {
        return $this->belongsTo(Bng::class);
    }
}
