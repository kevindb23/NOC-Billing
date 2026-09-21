<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltOntServiceProfile extends Model
{
    protected $fillable = ['olt_id', 'profile_id', 'profile_name', 'eth_port_count', 'port_modes', 'status', 'notes'];

    protected $casts = ['eth_port_count' => 'integer', 'port_modes' => 'array'];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }
}
