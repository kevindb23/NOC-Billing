<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = ['organization_id', 'customer_number', 'customer_type', 'legal_name', 'first_name', 'last_name', 'email', 'phone', 'status', 'notes'];
    public function organization() { return $this->belongsTo(Organization::class); }
    public function billingAccounts() { return $this->hasMany(BillingAccount::class); }
    public function subscriberServices() { return $this->hasMany(SubscriberService::class); }
}
