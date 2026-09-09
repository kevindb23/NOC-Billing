<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $fillable = ['organization_id', 'payment_id', 'invoice_id', 'amount_minor'];
    public function payment() { return $this->belongsTo(Payment::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
