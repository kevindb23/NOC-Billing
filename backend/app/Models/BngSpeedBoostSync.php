<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BngSpeedBoostSync extends Model
{
    protected $fillable = ['bng_speed_boost_id', 'username', 'rate_value', 'applied_at'];
    protected $casts = ['applied_at' => 'datetime'];

    public function speedBoost(): BelongsTo { return $this->belongsTo(BngSpeedBoost::class, 'bng_speed_boost_id'); }
}
