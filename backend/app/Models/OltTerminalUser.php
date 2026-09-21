<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltTerminalUser extends Model
{
    protected $fillable = [
        'olt_id', 'username', 'profile_name', 'password', 'privilege_level',
        'reenter_limit', 'appended_info', 'status', 'notes',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'privilege_level' => 'integer',
        'reenter_limit' => 'integer',
    ];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }
}
