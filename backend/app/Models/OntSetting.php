<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OntSetting extends Model
{
    protected $fillable = ['do_not_allow_rogue_onus', 'ont_id_capacity_per_port'];

    protected function casts(): array
    {
        return ['do_not_allow_rogue_onus' => 'boolean', 'ont_id_capacity_per_port' => 'integer'];
    }
}
