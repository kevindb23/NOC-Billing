<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class BillingStatement extends Model
{
    use HasPublicId;

    protected $fillable = ['billing_account_id', 'subscription_id', 'invoice_id', 'statement_number', 'status', 'issue_date', 'due_date', 'billing_period_start', 'billing_period_end', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor', 'amount_paid_minor', 'balance_due_minor', 'paid_at'];
    protected $casts = ['issue_date' => 'date', 'due_date' => 'date', 'billing_period_start' => 'date', 'billing_period_end' => 'date', 'paid_at' => 'datetime'];
    public function billingAccount() { return $this->belongsTo(BillingAccount::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
