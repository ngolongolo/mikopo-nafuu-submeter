<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DisbursementBatch extends Model {protected $guarded=[];protected function casts():array{return ['batch_date'=>'date','paid_at'=>'datetime'];}public function items(){return $this->hasMany(DisbursementBatchItem::class);}public function creator(){return $this->belongsTo(User::class,'created_by');}public function payer(){return $this->belongsTo(User::class,'paid_by');}}
