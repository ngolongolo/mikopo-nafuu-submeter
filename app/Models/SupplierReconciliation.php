<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierReconciliation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'transferred_at' => 'date',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(SupplierProfile::class, 'supplier_profile_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
