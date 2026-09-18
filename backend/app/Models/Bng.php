<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Bng extends Model { use HasPublicId, SoftDeletes; protected $fillable=['name','vendor','model','management_endpoint','preferred_transport','ssh_username','ssh_password','session_requested','status','notes','parent_interface','egress_interface']; protected $hidden=['ssh_password']; protected function casts(): array { return ['ssh_username'=>'encrypted','ssh_password'=>'encrypted','session_requested'=>'boolean']; } public function cgnatPolicies(): HasMany { return $this->hasMany(BngCgnatPolicy::class); } public function forwardingRules(): HasMany { return $this->hasMany(BngForwardingRule::class); } public function radiusServers(): HasMany { return $this->hasMany(BngRadiusServer::class); } }
