<?php

namespace App\Support;

use RuntimeException;

class PhoneNumber
{
    public static function normalize(string $value): string
    {
        $phone = preg_replace('/\D+/', '', $value);
        if (str_starts_with($phone, '0')) {
            $phone = '255'.substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            $phone = '255'.$phone;
        }
        if (!preg_match('/^255\d{9}$/', $phone)) {
            throw new RuntimeException('A valid Tanzanian phone number is required.');
        }

        return $phone;
    }

    public static function sanitize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return self::normalize($value);
        } catch (RuntimeException) {
            return trim($value);
        }
    }
}
