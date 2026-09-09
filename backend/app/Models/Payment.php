<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasPublicId;
    protected $fillable = ['organization_id', 'billing_account_id', 'payment_number', 'amount_minor', 'currency', 'payment_method', 'reference', 'idempotency_key', 'status', 'received_at'];
    protected $casts = ['received_at' => 'datetime'];
    public function billingAccount() { return $this->belongsTo(BillingAccount::class); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
}
