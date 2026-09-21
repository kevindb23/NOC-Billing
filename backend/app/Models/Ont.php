<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ont extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'olt_id',
        'acs_server_id',
        'frame',
        'slot',
        'pon_port',
        'ont_id',
        'serial_number',
        'name',
        'status',
        'notes',
        'last_discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'frame' => 'integer',
            'slot' => 'integer',
            'pon_port' => 'integer',
            'acs_server_id' => 'integer',
            'ont_id' => 'integer',
            'last_discovered_at' => 'datetime',
        ];
    }

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function acsServer()
    {
        return $this->belongsTo(AcsServer::class);
    }
}
