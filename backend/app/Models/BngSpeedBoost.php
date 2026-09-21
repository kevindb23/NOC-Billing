<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BngSpeedBoost extends Model
{
    use HasPublicId;

    protected $fillable = [
        'bng_id', 'bng_radius_server_id', 'plan_id', 'download_kbps', 'upload_kbps',
        'status', 'last_error', 'last_applied_at', 'synced_user_count',
    ];

    protected $casts = [
        'download_kbps' => 'integer',
        'upload_kbps' => 'integer',
        'last_applied_at' => 'datetime',
        'synced_user_count' => 'integer',
    ];

    public function bng(): BelongsTo { return $this->belongsTo(Bng::class); }
    public function radiusServer(): BelongsTo { return $this->belongsTo(BngRadiusServer::class, 'bng_radius_server_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function syncs(): HasMany { return $this->hasMany(BngSpeedBoostSync::class); }
}
