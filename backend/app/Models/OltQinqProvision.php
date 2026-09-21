<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltQinqProvision extends Model
{
    protected $fillable = ['olt_id', 'outer_vlan', 'inner_vlan', 'qinq_type', 'service_port_id', 'frame', 'slot', 'port_number', 'ont_line_profile', 'profile_id', 'dba_profile_id', 'tr069_management_enabled', 'tr069_ip_index', 'omcc_encrypt_enabled', 'port', 'name', 'status', 'notes'];

    protected $casts = [
        'tr069_management_enabled' => 'boolean',
        'tr069_ip_index' => 'integer',
        'omcc_encrypt_enabled' => 'boolean',
    ];
}
