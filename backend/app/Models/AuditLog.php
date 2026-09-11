<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['organization_id', 'actor_user_id', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'correlation_id', 'ip_address', 'user_agent'];
    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
