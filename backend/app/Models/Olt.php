<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Olt extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'name', 'vendor', 'model', 'management_endpoint', 'preferred_transport', 'ssh_username', 'ssh_password', 'session_requested', 'dba_profile_start_id', 'status', 'notes',
    ];

    protected $hidden = ['ssh_password'];

    protected function casts(): array
    {
        return ['ssh_password' => 'encrypted', 'session_requested' => 'boolean', 'dba_profile_start_id' => 'integer'];
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
}
