<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltVlanProvision extends Model
{
    protected $fillable = ['olt_id', 'vlan_id', 'name', 'service_mode', 'frame', 'slot', 'port_number', 'port', 'status', 'notes'];
}
