<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payout extends Model {protected $guarded=[];protected function casts():array{return ['paid_at'=>'datetime','platform_commission_booked_at'=>'datetime'];}public function loan(){return $this->belongsTo(Loan::class);}public function batch(){return $this->belongsTo(DisbursementBatch::class,'disbursement_batch_id');}}
