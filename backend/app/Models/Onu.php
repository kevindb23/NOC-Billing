<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Onu extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'vendor',
        'model',
        'serial_number',
        'quantity',
        'status',
        'purchase_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'purchase_date' => 'date:Y-m-d',
        ];
    }
}
