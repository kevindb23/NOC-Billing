<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'subscription_id', 'description', 'quantity', 'unit_amount_minor', 'line_total_minor', 'tax_minor', 'service_period_start', 'service_period_end'];
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
}
