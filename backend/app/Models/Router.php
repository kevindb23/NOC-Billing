<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Router extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'name',
        'vendor',
        'model',
        'management_endpoint',
        'preferred_transport',
        'ssh_username',
        'ssh_password',
        'session_requested',
        'status',
    ];

    protected $hidden = [
        'ssh_username',
        'ssh_password',
    ];

    protected function casts(): array
    {
        return [
            'ssh_username' => 'encrypted',
            'ssh_password' => 'encrypted',
            'session_requested' => 'boolean',
        ];
    }
}
