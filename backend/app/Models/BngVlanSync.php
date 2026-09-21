<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BngVlanSync extends Model
{
    use HasPublicId;

    protected $fillable = ['bng_id', 'olt_id', 'vlan_mode', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function bng(): BelongsTo { return $this->belongsTo(Bng::class); }
    public function olt(): BelongsTo { return $this->belongsTo(Olt::class); }
    public function interfaces(): HasMany { return $this->hasMany(BngVlanInterface::class, 'bng_id', 'bng_id')->whereColumn('olt_id', 'bng_vlan_syncs.olt_id'); }
}
