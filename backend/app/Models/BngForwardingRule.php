<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BngForwardingRule extends Model
{
    use HasPublicId;

    protected $fillable = [
        'bng_id',
        'name',
        'customer_interface',
        'internet_interface',
        'status',
        'notes',
    ];

    public function bng(): BelongsTo
    {
        return $this->belongsTo(Bng::class);
    }
}
