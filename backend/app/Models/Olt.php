<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Olt extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'name', 'vendor', 'model', 'management_endpoint', 'preferred_transport', 'ssh_username', 'ssh_password', 'session_requested', 'dba_profile_start_id', 'ont_service_profile_start_id', 'ont_wan_profile_start_id', 'ont_tr069_profile_start_id', 'ont_line_profile_start_id', 's_vlan_start_id', 'c_vlan_start_id', 'tr069_vlan_start_id', 'vlan_start_id', 'ont_id_capacity_per_port', 'terminal_user_security_enabled', 'terminal_user_security_length', 'status', 'notes',
    ];

    protected $hidden = ['ssh_password'];

    protected function casts(): array
    {
        return ['ssh_password' => 'encrypted', 'session_requested' => 'boolean', 'dba_profile_start_id' => 'integer', 'ont_service_profile_start_id' => 'integer', 'ont_wan_profile_start_id' => 'integer', 'ont_tr069_profile_start_id' => 'integer', 'ont_line_profile_start_id' => 'integer', 's_vlan_start_id' => 'integer', 'c_vlan_start_id' => 'integer', 'tr069_vlan_start_id' => 'integer', 'vlan_start_id' => 'integer', 'ont_id_capacity_per_port' => 'integer', 'terminal_user_security_enabled' => 'boolean', 'terminal_user_security_length' => 'integer'];
    }

    public function vlanProvisions()
    {
        return $this->hasMany(OltVlanProvision::class);
    }

    public function qinqProvisions()
    {
        return $this->hasMany(OltQinqProvision::class);
    }

    public function dbaProfiles()
    {
        return $this->hasMany(OltDbaProfile::class);
    }

    public function ontServiceProfiles()
    {
        return $this->hasMany(OltOntServiceProfile::class);
    }

    public function ontWanProfiles()
    {
        return $this->hasMany(OltOntWanProfile::class);
    }

    public function ontTr069ServerProfiles()
    {
        return $this->hasMany(OltOntTr069ServerProfile::class);
    }

    public function terminalUsers()
    {
        return $this->hasMany(OltTerminalUser::class);
    }

    public function onts()
    {
        return $this->hasMany(Ont::class);
    }

    public function activationPresets()
    {
        return $this->hasMany(ActivationPreset::class);
    }

    public function bngVlanSyncs(): HasMany
    {
        return $this->hasMany(BngVlanSync::class);
    }

    public function bngVlanInterfaces(): HasMany
    {
        return $this->hasMany(BngVlanInterface::class);
    }
}
