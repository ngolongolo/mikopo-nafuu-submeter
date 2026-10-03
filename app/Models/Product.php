<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Product extends Model {
 protected $guarded=[];
 protected function casts():array{return ['active'=>'boolean','auto_approve'=>'boolean','commission_financed'=>'boolean','max_loans_per_customer'=>'integer'];}
 public function financiers(){return $this->belongsToMany(Financier::class);}
 public function quote():array{return ['product_name'=>$this->name,'category'=>$this->category,'value'=>(int)$this->value,'down_payment'=>(int)$this->down_payment,'commission'=>(int)$this->commission,'commission_financed'=>$this->commission_financed,'upfront'=>(int)$this->down_payment+($this->commission_financed?0:(int)$this->commission),'principal'=>(int)$this->principal,'finance_charge'=>(int)$this->finance_charge,'repayment_total'=>(int)$this->principal+(int)$this->finance_charge,'installments'=>(int)$this->installments];}
}
