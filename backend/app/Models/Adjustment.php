<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Adjustment extends Model
{
    protected $fillable = ['billing_account_id', 'invoice_id', 'adjustment_type', 'amount_minor', 'reason', 'approved_by', 'status'];
    public function billingAccount() { return $this->belongsTo(BillingAccount::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
