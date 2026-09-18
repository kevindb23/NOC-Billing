<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = ['customer_number', 'customer_type', 'legal_name', 'first_name', 'last_name', 'email', 'phone', 'portal_username', 'portal_password', 'ppp_username', 'ppp_password', 'status', 'notes'];
    protected $hidden = ['portal_password', 'ppp_password'];
    protected function casts(): array { return ['portal_password' => 'encrypted', 'ppp_password' => 'encrypted']; }
    public function billingAccounts() { return $this->hasMany(BillingAccount::class); }
    public function subscriberServices() { return $this->hasMany(SubscriberService::class); }
}
