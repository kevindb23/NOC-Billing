<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BngCgnatPolicy extends Model
{
    use HasPublicId;

    protected $fillable = [
        'bng_id',
        'name',
        'subscriber_network',
        'subscriber_interface',
        'internet_interface',
        'public_ip_mode',
        'public_ip_start',
        'public_ip_end',
        'local_bypass_network',
        'status',
        'notes',
    ];

    public function bng(): BelongsTo
    {
        return $this->belongsTo(Bng::class);
    }
}
