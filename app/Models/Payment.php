<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model {protected $guarded=[]; protected function casts():array {return ['paid_at'=>'datetime','confirmed_at'=>'datetime','purchase_amount'=>'integer'];} public function loan(){return $this->belongsTo(Loan::class);} public function allocations(){return $this->hasMany(Allocation::class);} public function supplierApiToken(){return $this->belongsTo(SupplierApiToken::class);}}
