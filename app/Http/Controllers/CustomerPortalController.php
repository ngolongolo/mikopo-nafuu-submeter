<?php

namespace App\Http\Controllers;

use App\Models\CustomerLead;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CustomerPortalController
{
    public function __construct(private readonly SmsService $sms)
    {
    }

    public function login()
    {
        return view('customer-portal-login');
    }

    public function requestCode(Request $request)
    {
        $validated = $request->validate(['phone' => 'required|string|max:30']);
        $phone = $this->normalize($validated['phone']);

        if (!$this->hasCustomerRecords($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'No application was found for this mobile number.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $request->session()->put('customer_portal_challenge', [
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10)->timestamp,
            'sent_at' => now()->timestamp,
        ]);

        try {
            $this->sms->send($phone, "Your Mikopo Nafuu tracking code is {$code}. It expires in 10 minutes.");
        } catch (RuntimeException $exception) {
            report($exception);
            $request->session()->forget('customer_portal_challenge');

            return back()->withInput()->withErrors(['phone' => 'We could not send the tracking code. Please try again shortly.']);
        }

        return redirect('/track/verify')->with('success', 'We sent a six-digit tracking code to '.$this->maskPhone($phone).'.');
    }

    public function verifyForm(Request $request)
    {
        if (!$request->session()->has('customer_portal_challenge')) {
            return redirect('/track');
        }

        return view('customer-portal-verify', [
            'maskedPhone' => $this->maskPhone($request->session()->get('customer_portal_challenge.phone')),
        ]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate(['code' => 'required|digits:6']);
        $challenge = $request->session()->get('customer_portal_challenge');

        if (!$challenge || now()->timestamp > ($challenge['expires_at'] ?? 0) || ($challenge['attempts'] ?? 0) >= 5) {
            $request->session()->forget('customer_portal_challenge');
            throw ValidationException::withMessages(['code' => 'This tracking code expired. Request a new code.']);
        }

        if (!Hash::check($validated['code'], $challenge['code_hash'])) {
            $challenge['attempts']++;
            $request->session()->put('customer_portal_challenge', $challenge);
            throw ValidationException::withMessages(['code' => 'The tracking code is incorrect.']);
        }

        $request->session()->forget('customer_portal_challenge');
        $request->session()->put('customer_portal_phone', $challenge['phone']);
        $request->session()->regenerate();

        return redirect('/track/dashboard');
    }

    public function dashboard(Request $request)
    {
        $phone = $request->session()->get('customer_portal_phone');
        if (!$phone) {
            return redirect('/track');
        }

        $leads = CustomerLead::query()
            ->where('phone_normalized', $phone)
            ->with(['product', 'supplier', 'application'])
            ->latest()
            ->get();
        $customer = User::query()
            ->where('role', 'customer')
            ->whereIn('phone', $this->phoneVariants($phone))
            ->first();
        $applications = $customer
            ? $customer->applications()->with(['product', 'loan.installments', 'loan.payments'])->latest()->get()
            : collect();

        return view('customer-portal-dashboard', compact('phone', 'leads', 'customer', 'applications'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['customer_portal_phone', 'customer_portal_challenge']);
        $request->session()->regenerateToken();

        return redirect('/track');
    }

    private function hasCustomerRecords(string $phone): bool
    {
        return CustomerLead::where('phone_normalized', $phone)->exists()
            || User::where('role', 'customer')->whereIn('phone', $this->phoneVariants($phone))->exists();
    }

    private function normalize(string $phone): string
    {
        try {
            return $this->sms->normalizePhoneNumber($phone);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Tanzanian mobile number.']);
        }
    }

    private function phoneVariants(string $phone): array
    {
        $local = '0'.substr($phone, 3);

        return [$phone, '+'.$phone, $local, substr($phone, 3)];
    }

    private function maskPhone(string $phone): string
    {
        return substr($phone, 0, 6).'•••'.substr($phone, -3);
    }
}
