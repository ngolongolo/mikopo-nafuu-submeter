<?php

namespace App\Http\Middleware;

use App\Models\SupplierApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AuthenticateSupplierApi
{
    public function handle(Request $request, Closure $next)
    {
        $plainToken = $request->bearerToken();
        $token = $this->signedToken($request) ?? ($plainToken
            ? SupplierApiToken::with('supplier.users')->where('token_hash', hash('sha256', $plainToken))->whereNull('revoked_at')->first()
            : null);

        if (!$token || !$token->supplier?->active) {
            return response()->json(['message' => 'Invalid or inactive supplier API key.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('supplier_api_token', $token);
        $request->attributes->set('supplier_profile', $token->supplier);

        return $next($request);
    }

    private function signedToken(Request $request): ?SupplierApiToken
    {
        $key = $request->header('X-API-Key');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        if (!$key || !$timestamp || !$signature || !ctype_digit((string) $timestamp)) {
            return null;
        }
        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            return null;
        }

        $token = SupplierApiToken::with('supplier.users')->where('key_id', $key)->whereNull('revoked_at')->first();
        if (!$token?->encrypted_secret) {
            return null;
        }

        try {
            $secret = Crypt::decryptString($token->encrypted_secret);
        } catch (\Throwable) {
            return null;
        }

        $canonical = implode("\n", [
            $timestamp,
            strtoupper($request->method()),
            '/'.$request->path(),
            hash('sha256', $request->getContent()),
        ]);
        $expected = hash_hmac('sha256', $canonical, $secret);

        return hash_equals($expected, strtolower($signature)) ? $token : null;
    }
}
