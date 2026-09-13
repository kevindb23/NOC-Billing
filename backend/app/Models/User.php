<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->public_id ??= (string) str()->ulid();
        });
    }

    public function roles() { return $this->belongsToMany(Role::class, 'role_assignments')->withTimestamps(); }
    public function emailSettings() { return $this->hasOne(OrganizationEmailSetting::class); }
    public function notificationSettings() { return $this->hasOne(OrganizationNotificationSetting::class); }
    public function paymongoSettings() { return $this->hasOne(OrganizationPaymongoSetting::class); }
}
