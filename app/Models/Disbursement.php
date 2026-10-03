<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Disbursement extends Model {protected $guarded=[]; protected function casts():array {return ['confirmed_at'=>'datetime'];} public function batch(){return $this->belongsTo(DisbursementBatch::class,'disbursement_batch_id');} }
