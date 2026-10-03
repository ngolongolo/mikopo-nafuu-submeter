<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DisbursementBatchItem extends Model {protected $guarded=[];public function batch(){return $this->belongsTo(DisbursementBatch::class,'disbursement_batch_id');}public function loan(){return $this->belongsTo(Loan::class);}}
