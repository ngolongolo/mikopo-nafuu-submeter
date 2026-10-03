<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Installment extends Model {protected $guarded=[]; protected function casts():array {return ['due_date'=>'date'];} public function loan(){return $this->belongsTo(Loan::class);} public function outstanding():int{return $this->principal+$this->charge-$this->paid_principal-$this->paid_charge;}}
