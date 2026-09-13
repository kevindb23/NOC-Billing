<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanVersion extends Model
{
    protected $fillable = ['plan_id', 'version', 'recurring_price_minor', 'setup_fee_minor', 'currency', 'download_kbps', 'upload_kbps', 'effective_from', 'effective_until', 'status'];
    protected $casts = ['effective_from' => 'date', 'effective_until' => 'date', 'recurring_price_minor' => 'integer', 'setup_fee_minor' => 'integer'];
    public function plan() { return $this->belongsTo(Plan::class); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
}
