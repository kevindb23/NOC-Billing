<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationEmailSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'provider', 'host', 'port', 'encryption', 'username', 'password', 'from_name', 'from_email',
    ];

    protected $hidden = ['password'];

    protected $casts = ['port' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
