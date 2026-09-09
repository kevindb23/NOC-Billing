<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, HasPublicId;
    protected $fillable = ['organization_id', 'billing_account_id', 'invoice_number', 'status', 'issue_date', 'due_date', 'billing_period_start', 'billing_period_end', 'currency', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'amount_paid_minor', 'balance_due_minor', 'voided_at'];
    protected $casts = ['issue_date' => 'date', 'due_date' => 'date', 'billing_period_start' => 'date', 'billing_period_end' => 'date', 'voided_at' => 'datetime'];
    public function billingAccount() { return $this->belongsTo(BillingAccount::class); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function paymentAllocations() { return $this->hasMany(PaymentAllocation::class); }
}
