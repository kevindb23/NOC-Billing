<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    protected $fillable = ['organization_id', 'billing_account_id', 'source_type', 'source_id', 'amount_minor', 'remaining_minor', 'reason', 'status'];
    public function billingAccount() { return $this->belongsTo(BillingAccount::class); }
}
