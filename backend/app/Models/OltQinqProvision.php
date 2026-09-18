<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltQinqProvision extends Model
{
    protected $fillable = ['olt_id', 'outer_vlan', 'inner_vlan', 'qinq_type', 'service_port_id', 'frame', 'slot', 'port_number', 'ont_line_profile', 'profile_id', 'dba_profile_id', 'port', 'name', 'status', 'notes'];
}
