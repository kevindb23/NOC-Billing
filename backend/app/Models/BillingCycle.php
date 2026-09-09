<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCycle extends Model
{
    protected $fillable = ['organization_id', 'name', 'interval_unit', 'interval_count', 'billing_day', 'grace_days', 'status'];
    public function organization() { return $this->belongsTo(Organization::class); }
    public function plans() { return $this->hasMany(Plan::class); }
}
