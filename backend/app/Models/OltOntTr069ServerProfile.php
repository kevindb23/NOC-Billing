<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltOntTr069ServerProfile extends Model
{
    protected $fillable = ['olt_id', 'profile_id', 'profile_name', 'url', 'username', 'password', 'status', 'notes'];
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted'];
    }

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }
}
