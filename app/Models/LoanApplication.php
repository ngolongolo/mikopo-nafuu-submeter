<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    protected $guarded = [];
    protected $table = 'applications';

    protected function casts(): array
    {
        return ['terms' => 'array', 'accepted_at' => 'datetime'];
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = PhoneNumber::sanitize($value);
    }

    public function customer() { return $this->belongsTo(User::class, 'user_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function financier() { return $this->belongsTo(Financier::class); }
    public function loan() { return $this->hasOne(Loan::class, 'application_id'); }
    public function upfrontPayment() { return $this->hasOne(ApplicationUpfrontPayment::class, 'application_id'); }
}
