<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SupplierIntegrationController
{
    public function documentation(Request $request)
    {
        $supplier = $request->user()->supplierProfiles()->first() ?? $request->user()->supplierProfile;
        abort_unless($supplier && $supplier->active, 403);

        $credentials = $supplier->apiTokens()
            ->whereNull('revoked_at')
            ->whereNotNull('key_id')
            ->latest()
            ->get()
            ->map(function ($credential) {
                try {
                    $credential->plain_secret = Crypt::decryptString($credential->encrypted_secret);
                } catch (\Throwable) {
                    $credential->plain_secret = null;
                }
                return $credential;
            });

        return view('supplier-api-documentation', compact('supplier', 'credentials'));
    }
}
