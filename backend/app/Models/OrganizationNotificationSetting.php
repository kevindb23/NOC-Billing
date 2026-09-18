<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationNotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'telegram_enabled', 'telegram_bot_token', 'telegram_chat_id'];
    protected $hidden = ['telegram_bot_token'];
    protected $casts = ['telegram_enabled' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
}
