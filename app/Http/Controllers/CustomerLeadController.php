<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CustomerLead;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Services\SmsService;
use App\Services\Visibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CustomerLeadController
{
    public function __construct(private readonly SmsService $sms)
    {
    }

    public function landing()
    {
        $products = Product::query()
            ->whereIn('category', ['submeter', 'token'])
            ->where('active', true)
            ->whereHas('financiers', fn ($query) => $query->where('active', true))
            ->whereHas('suppliers', fn ($query) => $query->where('active', true))
            ->orderBy('value')
            ->get();
        $productOptions = $products->map(function (Product $product) {
            $terms = $product->quote();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'description' => $product->description,
                'value' => (int) $product->value,
                'upfront' => (int) $terms['upfront'],
                'principal' => (int) $terms['principal'],
                'finance_charge' => (int) $terms['finance_charge'],
                'commission' => (int) $terms['commission'],
                'commission_financed' => (bool) $terms['commission_financed'],
                'down_payment' => (int) $terms['down_payment'],
                'repayment_total' => (int) $terms['repayment_total'],
                'installments' => (int) $terms['installments'],
                'fingerprint' => $this->fingerprint($product),
            ];
        });
        $locations = json_decode(file_get_contents(resource_path('data/tanzania_locations.json')), true);

        return view('customer-lead-landing', compact('products', 'productOptions', 'locations'));
    }

    public function store(Request $request)
    {
        $productId = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ])['product_id'];
        $product = Product::query()
            ->whereKey($productId)
            ->whereIn('category', ['submeter', 'token'])
            ->where('active', true)
            ->whereHas('financiers', fn ($query) => $query->where('active', true))
            ->whereHas('suppliers', fn ($query) => $query->where('active', true))
            ->firstOrFail();
        $isToken = $product->category === 'token';
        $locations = json_decode(file_get_contents(resource_path('data/tanzania_locations.json')), true);
        $region = (string) $request->input('region');
        $district = (string) $request->input('district');
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'first_name' => ($isToken ? 'nullable' : 'required').'|string|max:80',
            'middle_name' => 'nullable|string|max:80',
            'last_name' => ($isToken ? 'nullable' : 'required').'|string|max:80',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:200',
            'region' => [$isToken ? 'nullable' : 'required', Rule::in(array_keys($locations))],
            'district' => [$isToken ? 'nullable' : 'required', Rule::in(array_keys($locations[$region] ?? []))],
            'ward' => [$isToken ? 'nullable' : 'required', Rule::in($locations[$region][$district] ?? [])],
            'address' => ($isToken ? 'nullable' : 'required').'|string|max:1000',
            'utility_type' => ($isToken ? 'nullable' : 'required').'|in:electricity,water',
            'meter_number' => ($isToken ? 'required' : 'nullable').'|string|max:80',
            'meter_quantity' => ($isToken ? 'nullable' : 'required').'|integer|min:1|max:5',
            'financing_amount' => 'required|integer|min:1|max:1000000000',
            'terms_fingerprint' => 'required|string|size:64',
            'accepted' => 'accepted',
        ]);

        if (!hash_equals($this->fingerprint($product), $validated['terms_fingerprint'])) {
            throw ValidationException::withMessages(['accepted' => 'The financing terms changed while you were applying. Review the updated summary and accept again.']);
        }
        $meterQuantity = $product->category === 'submeter' ? (int) $validated['meter_quantity'] : 1;
        $expectedSubmeterAmount = (int) $product->value * $meterQuantity;
        if ($product->category === 'submeter' && (int) $validated['financing_amount'] !== $expectedSubmeterAmount) {
            throw ValidationException::withMessages(['financing_amount' => 'The submeter request value for '.$meterQuantity.' meter'.($meterQuantity === 1 ? '' : 's').' is '.number_format($expectedSubmeterAmount).' TZS.']);
        }
        if ($product->category === 'token' && ((int) $validated['financing_amount'] < 2000 || (int) $validated['financing_amount'] > (int) $product->value)) {
            throw ValidationException::withMessages(['financing_amount' => 'Token financing must be between 2,000 and '.number_format($product->value).' TZS.']);
        }
        try {
            $normalizedPhone = $this->sms->normalizePhoneNumber($validated['phone']);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Tanzanian mobile number.']);
        }
        if (CustomerLead::where('phone_normalized', $normalizedPhone)->where('request_type', $product->category)->exists()) {
            throw ValidationException::withMessages(['product_id' => 'An application for this type already exists for this mobile number. Use Track application to follow its progress.']);
        }
        $meterNumber = $isToken ? preg_replace('/\s+/', '', strtoupper(trim($validated['meter_number']))) : null;
        if ($isToken && CustomerLead::where('request_type', 'token')->where('meter_number', $meterNumber)->exists()) {
            throw ValidationException::withMessages(['meter_number' => 'A token financing application already exists for this meter. Use Customer sign in to track it.']);
        }

        $terms = $this->termsForAmount($product, (int) $validated['financing_amount'], $meterQuantity);
        $lead = CustomerLead::create([
            'public_id' => (string) Str::ulid(),
            'reference' => 'LEAD-'.Str::upper(Str::random(10)),
            'product_id' => $product->id,
            'request_type' => $product->category,
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'phone' => $normalizedPhone,
            'phone_normalized' => $normalizedPhone,
            'email' => $validated['email'] ?? null,
            'region' => $validated['region'] ?? null,
            'district' => $validated['district'] ?? null,
            'ward' => $validated['ward'] ?? null,
            'address' => $validated['address'] ?? null,
            'utility_type' => $isToken ? 'electricity' : $validated['utility_type'],
            'meter_number' => $meterNumber,
            'meter_quantity' => $meterQuantity,
            'financing_amount' => $validated['financing_amount'],
            'terms' => $terms,
            'terms_accepted_at' => now(),
            'status' => 'unconfirmed',
        ]);

        try {
            $this->sendConfirmationCode($lead);
        } catch (RuntimeException $exception) {
            report($exception);

            return redirect('/requests/'.$lead->public_id)
                ->with('otp_error', 'Your application was saved, but we could not send the confirmation code. Please resend it.');
        }

        return redirect('/requests/'.$lead->public_id)
            ->with('success', 'Application saved. Enter the six-digit code sent by SMS to confirm it.');
    }

    public function received(CustomerLead $lead)
    {
        $lead->load(['product', 'supplier']);

        return view('customer-lead-received', compact('lead'));
    }

    public function confirm(Request $request, CustomerLead $lead)
    {
        $validated = $request->validate(['code' => 'required|digits:6']);
        if ($lead->status !== 'unconfirmed') {
            return redirect('/requests/'.$lead->public_id);
        }
        if (!$lead->confirmation_code_hash || !$lead->confirmation_expires_at || $lead->confirmation_expires_at->isPast() || $lead->confirmation_attempts >= 5) {
            throw ValidationException::withMessages(['code' => 'This confirmation code expired. Request a new code.']);
        }
        if (!Hash::check($validated['code'], $lead->confirmation_code_hash)) {
            $lead->increment('confirmation_attempts');
            throw ValidationException::withMessages(['code' => 'The confirmation code is incorrect.']);
        }

        $lead->update([
            'status' => 'new',
            'confirmed_at' => now(),
            'confirmation_code_hash' => null,
            'confirmation_attempts' => 0,
            'confirmation_expires_at' => null,
        ]);

        return redirect('/requests/'.$lead->public_id)->with('success', 'Application confirmed. It is now available to eligible suppliers.');
    }

    public function resendConfirmation(CustomerLead $lead)
    {
        if ($lead->status !== 'unconfirmed') {
            return redirect('/requests/'.$lead->public_id);
        }
        if ($lead->confirmation_sent_at?->gt(now()->subMinute())) {
            throw ValidationException::withMessages(['code' => 'Please wait one minute before requesting another code.']);
        }

        try {
            $this->sendConfirmationCode($lead);
        } catch (RuntimeException $exception) {
            report($exception);
            throw ValidationException::withMessages(['code' => 'We could not send a new code. Please try again shortly.']);
        }

        return back()->with('success', 'A new confirmation code was sent by SMS.');
    }

    public function index(Request $request)
    {
        $supplier = $this->supplier($request);
        $productIds = $supplier->products()->pluck('products.id');
        $available = CustomerLead::where('status', 'new')->whereIn('product_id', $productIds);
        $owned = CustomerLead::where('supplier_profile_id', $supplier->id);
        $base = CustomerLead::where(fn ($query) => $query
            ->where(fn ($new) => $new->where('status', 'new')->whereIn('product_id', $productIds))
            ->orWhere('supplier_profile_id', $supplier->id));
        $stats = [
            'Available leads' => (clone $available)->count(),
            'Accepted' => (clone $owned)->where('status', 'accepted')->count(),
            'Onboarded' => (clone $owned)->where('status', 'onboarded')->count(),
        ];
        if ($request->filled('status')) {
            $base->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $search = $request->input('q');
            $base->where(fn ($query) => $query->where('reference', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
        }
        $leads = $base->with(['product', 'supplier', 'application'])->latest()->paginate(20)->withQueryString();

        return view('customer-leads', compact('supplier', 'leads', 'stats'));
    }

    public function accept(Request $request, CustomerLead $lead)
    {
        $supplier = $this->supplier($request);
        if ($lead->request_type === 'token') {
            $request->validate([
                'meter_exists' => 'accepted',
                'meter_eligible' => 'accepted',
                'meter_verification_note' => 'nullable|string|max:1000',
            ]);
        }
        $claimed = DB::transaction(function () use ($request, $lead, $supplier) {
            $locked = CustomerLead::lockForUpdate()->findOrFail($lead->id);
            if ($locked->status === 'accepted' && $locked->supplier_profile_id === $supplier->id) {
                return $locked;
            }
            if ($locked->status !== 'new') {
                throw ValidationException::withMessages(['lead' => 'This lead has already been accepted by another supplier.']);
            }
            if (!$supplier->active || !$supplier->products()->whereKey($locked->product_id)->exists()) {
                abort(403);
            }
            $locked->update([
                'status' => 'accepted',
                'supplier_profile_id' => $supplier->id,
                'claimed_by' => $request->user()->id,
                'claimed_at' => now(),
                'meter_exists' => $locked->request_type === 'token' ? true : null,
                'meter_eligible' => $locked->request_type === 'token' ? true : null,
                'meter_verification_note' => $locked->request_type === 'token' ? $request->input('meter_verification_note') : null,
                'meter_verified_by' => $locked->request_type === 'token' ? $request->user()->id : null,
                'meter_verified_at' => $locked->request_type === 'token' ? now() : null,
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'customer_lead.accepted',
                'entity' => 'CustomerLead',
                'entity_id' => $locked->id,
                'metadata' => [
                    'supplier_profile_id' => $supplier->id,
                    'meter_number' => $locked->meter_number,
                    'meter_verified' => $locked->request_type === 'token',
                ],
            ]);

            return $locked;
        });

        $message = $claimed->request_type === 'token'
            ? 'Meter existence and eligibility confirmed. Complete customer KYC to onboard the token request.'
            : 'Lead accepted. Complete KYC and book the upfront collection to onboard the customer.';

        return redirect('/customers/onboard?lead='.$claimed->public_id)->with('success', $message);
    }

    private function supplier(Request $request): SupplierProfile
    {
        $supplier = Visibility::supplierProfile($request->user());
        abort_unless($supplier && $supplier->active, 403);

        return $supplier;
    }

    private function termsForAmount(Product $product, int $amount, int $meterQuantity = 1): array
    {
        $terms = $product->quote();
        if ($product->category === 'token') {
            $terms['value'] = $amount;
            $terms['principal'] = $amount - $terms['down_payment'] + ($terms['commission_financed'] ? $terms['commission'] : 0);
            $terms['repayment_total'] = $terms['principal'] + $terms['finance_charge'];
        } elseif ($meterQuantity > 1) {
            foreach (['value', 'upfront', 'principal', 'finance_charge', 'commission', 'down_payment', 'repayment_total'] as $field) {
                $terms[$field] = (int) $terms[$field] * $meterQuantity;
            }
        }
        $terms['meter_quantity'] = $meterQuantity;

        return $terms;
    }

    private function sendConfirmationCode(CustomerLead $lead): void
    {
        $code = (string) random_int(100000, 999999);
        $lead->update([
            'confirmation_code_hash' => Hash::make($code),
            'confirmation_attempts' => 0,
            'confirmation_sent_at' => now(),
            'confirmation_expires_at' => now()->addMinutes(10),
        ]);
        $this->sms->send($lead->phone_normalized, "Confirm Mikopo Nafuu application {$lead->reference} with code {$code}. It expires in 10 minutes.");
    }

    private function fingerprint(Product $product): string
    {
        return hash('sha256', json_encode($product->quote()));
    }
}
