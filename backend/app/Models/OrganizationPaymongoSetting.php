<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationPaymongoSetting extends Model
{
    protected $fillable = ['user_id', 'enabled', 'environment', 'public_key', 'secret_key', 'webhook_secret'];

    protected $casts = ['enabled' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
}
