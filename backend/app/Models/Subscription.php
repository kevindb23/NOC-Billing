<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['organization_id', 'subscriber_service_id', 'billing_account_id', 'plan_version_id', 'status', 'starts_on', 'ends_on', 'next_billing_date', 'billing_day', 'price_snapshot_minor', 'currency_snapshot', 'plan_name_snapshot'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'next_billing_date' => 'date', 'price_snapshot_minor' => 'integer'];
    public function service() { return $this->belongsTo(SubscriberService::class, 'subscriber_service_id'); }
    public function billingAccount() { return $this->belongsTo(BillingAccount::class); }
    public function planVersion() { return $this->belongsTo(PlanVersion::class); }
    public function invoiceItems() { return $this->hasMany(InvoiceItem::class); }
}
