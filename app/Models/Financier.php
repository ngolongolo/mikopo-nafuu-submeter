<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Financier extends Model {protected $guarded=[]; protected function casts():array {return ['active'=>'boolean'];} public function products(){return $this->belongsToMany(Product::class);}}
