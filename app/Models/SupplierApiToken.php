<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierApiToken extends Model
{
    protected $guarded = [];
    protected $hidden = ['token_hash', 'encrypted_secret'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function supplier()
    {
        return $this->belongsTo(SupplierProfile::class, 'supplier_profile_id');
    }
}
