<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizationBillingSetting extends Model { protected $fillable = ['cycle_start_day','vat_rate','installation_amortization_months']; protected $casts = ['cycle_start_day' => 'integer','vat_rate' => 'float','installation_amortization_months' => 'integer']; }
