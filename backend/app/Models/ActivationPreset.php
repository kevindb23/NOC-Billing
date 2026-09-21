<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivationPreset extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'olt_id',
        'name',
        'dba_profile_id',
        'ont_line_profile_id',
        'ont_service_profile_id',
        'ont_wan_profile_id',
        'ont_tr069_server_profile_id',
    ];

    public function olt() { return $this->belongsTo(Olt::class); }
    public function dbaProfile() { return $this->belongsTo(OltDbaProfile::class, 'dba_profile_id'); }
    public function ontLineProfile() { return $this->belongsTo(OltQinqProvision::class, 'ont_line_profile_id'); }
    public function ontServiceProfile() { return $this->belongsTo(OltOntServiceProfile::class, 'ont_service_profile_id'); }
    public function ontWanProfile() { return $this->belongsTo(OltOntWanProfile::class, 'ont_wan_profile_id'); }
    public function ontTr069ServerProfile() { return $this->belongsTo(OltOntTr069ServerProfile::class, 'ont_tr069_server_profile_id'); }
    public function activations() { return $this->hasMany(Activation::class, 'activation_preset_id'); }
}
