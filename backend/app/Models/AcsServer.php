<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcsServer extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;
    protected $fillable = ['name', 'api_url', 'api_username', 'api_password', 'transport', 'status', 'ssh_username', 'ssh_password', 'ssh_port', 'notes', 'last_ssh_tested_at', 'last_api_tested_at', 'last_error'];
    protected $hidden = ['api_password', 'ssh_password'];
    protected function casts(): array { return ['api_password' => 'encrypted', 'ssh_password' => 'encrypted', 'ssh_port' => 'integer', 'last_ssh_tested_at' => 'datetime', 'last_api_tested_at' => 'datetime']; }
}
