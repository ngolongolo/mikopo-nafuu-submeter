<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'confirmed_at' => 'datetime', 'purchase_amount' => 'integer'];
    }

    public function setCustomerPhoneAttribute($value): void
    {
        $this->attributes['customer_phone'] = PhoneNumber::sanitize($value);
    }

    public function loan() { return $this->belongsTo(Loan::class); }
    public function allocations() { return $this->hasMany(Allocation::class); }
    public function supplierApiToken() { return $this->belongsTo(SupplierApiToken::class); }
    public function supplierReconciliation() { return $this->belongsTo(SupplierReconciliation::class); }
}
