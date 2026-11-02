<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailSmsFallback
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(User $user, Mailable $mail, string $smsMessage): bool
    {
        try {
            Mail::to($user->email, $user->name)->send($mail);
            return true;
        } catch (Throwable $exception) {
            Log::warning('Email delivery failed; attempting SMS fallback.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        if (!$user->phone) {
            Log::warning('SMS fallback skipped because the user has no phone number.', ['user_id' => $user->id]);
            return false;
        }

        try {
            $this->sms->send($user->phone, $smsMessage);
            return false;
        } catch (Throwable $exception) {
            Log::error('SMS fallback failed after email delivery failure.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
