<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activation extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'subscriber_service_id',
        'subscription_id',
        'olt_id',
        'activation_preset_id',
        'vlan_provision_id',
        'qinq_provision_id',
        'ont_id',
        'provisioning_type',
        'c_vlan',
        's_vlan',
        'status',
        'activated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'c_vlan' => 'integer',
            's_vlan' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    public function service() { return $this->belongsTo(SubscriberService::class, 'subscriber_service_id'); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function ont() { return $this->belongsTo(Ont::class); }
    public function olt() { return $this->belongsTo(Olt::class); }
    public function preset() { return $this->belongsTo(ActivationPreset::class, 'activation_preset_id'); }
    public function vlanProvision() { return $this->belongsTo(OltVlanProvision::class, 'vlan_provision_id'); }
    public function qinqProvision() { return $this->belongsTo(OltQinqProvision::class, 'qinq_provision_id'); }
}
