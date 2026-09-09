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
    public function customers() { return $this->hasMany(Customer::class); }
    public function billingAccounts() { return $this->hasMany(BillingAccount::class); }
    public function plans() { return $this->hasMany(Plan::class); }
}
