<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model {protected $guarded=[]; protected function casts():array {return ['paid_at'=>'datetime','confirmed_at'=>'datetime'];} public function loan(){return $this->belongsTo(Loan::class);} public function allocations(){return $this->hasMany(Allocation::class);}}
