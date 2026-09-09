<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory, HasPublicId;
    protected $fillable = ['organization_id', 'billing_cycle_id', 'code', 'name', 'service_type', 'description', 'status'];
    public function organization() { return $this->belongsTo(Organization::class); }
    public function billingCycle() { return $this->belongsTo(BillingCycle::class); }
    public function versions() { return $this->hasMany(PlanVersion::class); }
}
