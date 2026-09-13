<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriberService extends Model
{
    use HasFactory, HasPublicId;
    protected $fillable = ['customer_id', 'billing_account_id', 'service_number', 'service_type', 'status', 'activated_at', 'suspended_at', 'terminated_at', 'notes'];
    protected $casts = ['activated_at' => 'datetime', 'suspended_at' => 'datetime', 'terminated_at' => 'datetime'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function billingAccount() { return $this->belongsTo(BillingAccount::class); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
}
