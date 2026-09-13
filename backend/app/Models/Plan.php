<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory, HasPublicId;
    protected $fillable = ['billing_cycle_id', 'code', 'name', 'service_type', 'description', 'status'];
    public function billingCycle() { return $this->belongsTo(BillingCycle::class); }
    public function versions() { return $this->hasMany(PlanVersion::class); }
}
