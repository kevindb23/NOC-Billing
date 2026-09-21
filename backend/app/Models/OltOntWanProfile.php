<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OltOntWanProfile extends Model { protected $fillable = ['olt_id','profile_id','profile_name','nat_enabled','status','notes']; protected $casts = ['nat_enabled' => 'boolean']; public function olt() { return $this->belongsTo(Olt::class); } }
