<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

class CustomerLead extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'terms' => 'array',
            'financing_amount' => 'integer',
            'meter_quantity' => 'integer',
            'meter_exists' => 'boolean',
            'meter_eligible' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'confirmation_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'claimed_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'meter_verified_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function setPhoneAttribute($value): void
    {
        $phone = PhoneNumber::sanitize($value);
        $this->attributes['phone'] = $phone;
        $this->attributes['phone_normalized'] = $phone;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(SupplierProfile::class, 'supplier_profile_id');
    }

    public function claimant()
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
