<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = ['name', 'slug', 'status', 'timezone', 'default_currency'];

    public function users() { return $this->belongsToMany(User::class)->withPivot(['is_default', 'status'])->withTimestamps(); }
    public function roles() { return $this->hasMany(Role::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function billingAccounts() { return $this->hasMany(BillingAccount::class); }
    public function plans() { return $this->hasMany(Plan::class); }
    public function branding() { return $this->hasOne(OrganizationBranding::class); }
    public function emailSettings() { return $this->hasMany(OrganizationEmailSetting::class); }
    public function notificationSettings() { return $this->hasMany(OrganizationNotificationSetting::class); }
    public function paymongoSettings() { return $this->hasMany(OrganizationPaymongoSetting::class); }
}
