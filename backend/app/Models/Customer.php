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
    protected $appends = ['ppp_credentials_configured'];
    protected function casts(): array { return ['portal_password' => 'encrypted', 'ppp_password' => 'encrypted']; }
    public function getPppCredentialsConfiguredAttribute(): bool
    {
        return trim((string) $this->ppp_username) !== '' && trim((string) $this->ppp_password) !== '';
    }

    public function hasPppCredentials(): bool
    {
        return (bool) $this->ppp_credentials_configured;
    }

    public function billingAccounts() { return $this->hasMany(BillingAccount::class); }
    public function subscriberServices() { return $this->hasMany(SubscriberService::class); }
}
