<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingAccount extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = ['customer_id', 'account_number', 'currency', 'credit_limit_minor', 'status'];
    protected $casts = ['credit_limit_minor' => 'integer'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function subscriberServices() { return $this->hasMany(SubscriberService::class); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function billingStatements() { return $this->hasMany(BillingStatement::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}
