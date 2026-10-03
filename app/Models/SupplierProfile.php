<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class SupplierProfile extends Model {protected $guarded=[];protected function casts():array{return ['active'=>'boolean'];}public function user(){return $this->belongsTo(User::class);}public function products(){return $this->belongsToMany(Product::class,'product_supplier');}}
