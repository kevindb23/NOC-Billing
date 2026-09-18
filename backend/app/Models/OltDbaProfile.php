<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltDbaProfile extends Model
{
    protected $fillable = ['olt_id', 'profile_id', 'bandwidth_mbps', 'profile_name', 'status', 'notes'];
}
