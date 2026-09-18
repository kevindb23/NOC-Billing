<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizationGcashSetting extends Model { protected $fillable = ['user_id','enabled','account_name','mobile_number','instructions']; protected $casts = ['enabled' => 'boolean']; public function user() { return $this->belongsTo(User::class); } }
