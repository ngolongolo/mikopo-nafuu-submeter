<?php

namespace App\Http\Middleware;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizePhoneNumbers
{
    private const FIELDS = [
        'phone',
        'phone_number',
        'alternative_phone',
        'next_of_kin_phone',
        'customer_phone',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $sanitized = [];
        foreach (self::FIELDS as $field) {
            if ($request->exists($field) && is_string($request->input($field))) {
                $sanitized[$field] = PhoneNumber::sanitize($request->input($field));
            }
        }
        if ($sanitized !== []) {
            $request->merge($sanitized);
        }

        return $next($request);
    }
}
