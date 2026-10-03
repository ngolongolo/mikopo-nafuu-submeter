<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
 use Notifiable;
 protected $attributes=['role'=>'customer','submeter_qualifying_amount'=>100000,'token_qualifying_amount'=>5000];
 protected $fillable=['public_id','name','first_name','middle_name','last_name','email','password','phone','id_type','id_number','role','financier_id','onboarded_by','date_of_birth','gender','alternative_phone','region_district','ward_street','region','district','ward','address','occupation','monthly_income','next_of_kin','next_of_kin_phone','submeter_qualifying_amount','token_qualifying_amount'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array{return ['password'=>'hashed','date_of_birth'=>'date','submeter_qualifying_amount'=>'integer','token_qualifying_amount'=>'integer'];}
 public function financier(){return $this->belongsTo(Financier::class);}
 public function onboardedBy(){return $this->belongsTo(self::class,'onboarded_by');}
 public function onboardedCustomers(){return $this->hasMany(self::class,'onboarded_by');}
 public function applications(){return $this->hasMany(LoanApplication::class);}
 public function loans(){return $this->hasMany(Loan::class);}
}
