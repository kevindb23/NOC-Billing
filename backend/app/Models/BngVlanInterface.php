<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BngVlanInterface extends Model
{
    protected $fillable = ['bng_id', 'olt_id', 'vlan_mode', 'outer_vlan', 'inner_vlan', 'interface_name', 'status', 'last_error'];

    protected function casts(): array
    {
        return ['outer_vlan' => 'integer', 'inner_vlan' => 'integer'];
    }

    public function bng(): BelongsTo { return $this->belongsTo(Bng::class); }
    public function olt(): BelongsTo { return $this->belongsTo(Olt::class); }
}
