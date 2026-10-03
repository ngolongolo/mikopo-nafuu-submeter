<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class LoginOtp extends Model {protected $guarded=[];protected $hidden=['code_hash'];protected function casts():array{return ['expires_at'=>'datetime','sent_at'=>'datetime'];}public function user(){return $this->belongsTo(User::class);}}
