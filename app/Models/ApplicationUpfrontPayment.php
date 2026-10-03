<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApplicationUpfrontPayment extends Model {protected $guarded=[];protected function casts():array{return ['paid_at'=>'datetime'];}public function application(){return $this->belongsTo(LoanApplication::class);}public function recorder(){return $this->belongsTo(User::class,'recorded_by');}}
