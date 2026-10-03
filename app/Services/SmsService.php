<?php
namespace App\Services;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class SmsService
{
    public function send(string $phoneNumber, string $message): array
    {
        $apiId = config('services.sprint_sms.api_id');
        $password = config('services.sprint_sms.api_password');
        if (!$apiId || !$password)
            throw new RuntimeException('Sprint SMS credentials are not configured.');
        $phone = $this->normalizePhoneNumber($phoneNumber);
        $text = trim($message);
        if ($text === '')
            throw new RuntimeException('SMS message cannot be empty.');
        try {
            $response = Http::acceptJson()->timeout(config('services.sprint_sms.timeout', 15))->retry(2, 300)->get(config('services.sprint_sms.url'), ['api_id' => $apiId, 'api_password' => $password, 'sms_type' => 'T', 'encoding' => 'T', 'sender_id' => config('services.sprint_sms.sender_id', 'BLUETICK'), 'phonenumber' => $phone, 'textmessage' => $text]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to connect to the SMS provider.', previous: $exception);
        }
        if ($response->failed())
            throw new RuntimeException('SMS provider rejected the request with HTTP ' . $response->status() . '.');
        return ['successful' => true, 'phone_number' => $phone, 'provider_response' => $response->json() ?? $response->body()];
    }
    public function normalizePhoneNumber(string $phoneNumber): string
    {
        $phone = preg_replace('/\D+/', '', $phoneNumber);
        if (str_starts_with($phone, '0'))
            $phone = '255' . substr($phone, 1);
        elseif (strlen($phone) === 9)
            $phone = '255' . $phone;
        if (!preg_match('/^255\d{9}$/', $phone))
            throw new RuntimeException('A valid Tanzanian phone number is required.');
        return $phone;
    }
}
