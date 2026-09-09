<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceTransaction extends Model
{
    protected $fillable = ['organization_id', 'billing_account_id', 'invoice_id', 'payment_id', 'credit_id', 'adjustment_id', 'transaction_type', 'direction', 'amount_minor', 'currency', 'description', 'created_by'];
    public function billingAccount() { return $this->belongsTo(BillingAccount::class); }
}
