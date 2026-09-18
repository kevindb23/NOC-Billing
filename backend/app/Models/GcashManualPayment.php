<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GcashManualPayment extends Model { protected $fillable = ['user_id','invoice_id','statement_id','reference_number','amount_minor','currency','transferred_on','receipt_path','notes','status','reviewed_by','reviewed_at']; protected $casts = ['transferred_on' => 'date','reviewed_at' => 'datetime','amount_minor' => 'integer']; public function invoice() { return $this->belongsTo(Invoice::class); } public function statement() { return $this->belongsTo(BillingStatement::class, 'statement_id'); } public function user() { return $this->belongsTo(User::class); } public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); } }
