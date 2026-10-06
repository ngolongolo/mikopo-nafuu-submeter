<?php

namespace App\Http\Controllers;

use App\Models\{ApplicationUpfrontPayment, AuditLog, Loan, LoanApplication, Payment, Product, SupplierProfile, User};
use App\Services\LendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierApiController
{
    public function onboardCustomer(Request $request, LendingService $lending)
    {
        $productSelection = $request->validate(['product_code' => 'required|string|max:80']);
        $supplier = $this->supplier($request);
        $product = Product::where('code', $productSelection['product_code'])->whereIn('category', ['submeter', 'token'])->where('active', true)->first();

        if (!$product || !$supplier->products()->whereKey($product->id)->exists()) {
            throw ValidationException::withMessages(['product_code' => ['This active product is not assigned to your supplier.']]);
        }

        $locations = json_decode(file_get_contents(resource_path('data/tanzania_locations.json')), true);
        $region = (string) $request->input('region');
        $district = (string) $request->input('district');
        $submeter = $product->category === 'submeter';

        $validated = $request->validate([
            'first_name' => 'required|string|max:80',
            'middle_name' => 'nullable|string|max:80',
            'last_name' => 'required|string|max:80',
            'id_type' => 'required|in:national_id,driving_license,passport,voters_id',
            'id_number' => 'required|string|max:80|unique:users,id_number',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female',
            'phone_number' => 'required|string|max:30|unique:users,phone',
            'alternative_phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:200|unique:users,email',
            'region' => ['required', Rule::in(array_keys($locations))],
            'district' => ['required', Rule::in(array_keys($locations[$region] ?? []))],
            'ward' => ['required', Rule::in($locations[$region][$district] ?? [])],
            'address' => 'required|string|max:1000',
            'occupation' => 'required|string|max:150',
            'next_of_kin_name' => 'required|string|max:150',
            'next_of_kin_phone' => 'required|string|max:30',
            'product_code' => 'required|string|max:80',
            'utility_type' => 'required|in:electricity,water',
            'financing_amount' => 'required|integer|min:1|max:1000000000',
            'meter_number' => 'required|string|max:80',
            'device_serial' => ($submeter ? 'required' : 'nullable').'|string|max:100',
            'meter_brand_model' => ($submeter ? 'required' : 'nullable').'|string|max:150',
            'terms_accepted' => 'accepted',
            'quotation_confirmed' => 'accepted',
            'upfront_amount' => ($submeter ? 'required' : 'nullable').'|integer|min:0|max:1000000000',
            'upfront_channel' => ($submeter ? 'required' : 'nullable').'|in:bank,mobile_money',
            'upfront_paid_at' => ($submeter ? 'required' : 'nullable').'|date|before_or_equal:today',
            'upfront_receipt' => ($submeter ? 'required' : 'nullable').'|string|max:1000',
        ]);

        $financier = $product->financiers()->where('active', true)->inRandomOrder()->first();
        if (!$financier) {
            throw ValidationException::withMessages(['product_code' => ['No active financier is available for this product.']]);
        }

        $terms = $product->quote();
        if ($submeter && (int) $validated['financing_amount'] !== (int) $product->value) {
            throw ValidationException::withMessages(['financing_amount' => ['Submeter financing amount must equal '.number_format($product->value).' TZS.']]);
        }
        if (!$submeter) {
            if ((int) $validated['financing_amount'] < 2000 || (int) $validated['financing_amount'] > 5000) {
                throw ValidationException::withMessages(['financing_amount' => ['Token financing amount must be between 2,000 and 5,000 TZS.']]);
            }
            $terms['value'] = (int) $validated['financing_amount'];
            $terms['principal'] = $terms['value'] - $terms['down_payment'] + ($terms['commission_financed'] ? $terms['commission'] : 0);
            $terms['repayment_total'] = $terms['principal'] + $terms['finance_charge'];
        }
        if ($submeter && (int) $validated['upfront_amount'] !== (int) $terms['upfront']) {
            throw ValidationException::withMessages(['upfront_amount' => ['Upfront payment must equal '.number_format($terms['upfront']).' TZS.']]);
        }

        $actor = $supplier->user;
        [$customer, $application] = DB::transaction(function () use ($actor, $validated, $product, $financier, $terms, $submeter) {
            $name = trim(implode(' ', array_filter([$validated['first_name'], $validated['middle_name'] ?? null, $validated['last_name']])));
            $customer = User::create([
                'name' => $name,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone_number'],
                'alternative_phone' => $validated['alternative_phone'] ?? null,
                'id_type' => $validated['id_type'],
                'id_number' => $validated['id_number'],
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'],
                'region' => $validated['region'],
                'district' => $validated['district'],
                'ward' => $validated['ward'],
                'address' => $validated['address'],
                'occupation' => $validated['occupation'],
                'next_of_kin' => $validated['next_of_kin_name'],
                'next_of_kin_phone' => $validated['next_of_kin_phone'],
                'password' => Str::random(40),
                'role' => 'customer',
                'onboarded_by' => $actor->id,
            ]);
            $application = LoanApplication::create([
                'reference' => 'APP-'.Str::upper(Str::random(10)),
                'user_id' => $customer->id,
                'product_id' => $product->id,
                'financier_id' => $financier->id,
                'phone' => $validated['phone_number'],
                'id_type' => $validated['id_type'],
                'id_number' => $validated['id_number'],
                'address' => $validated['address'],
                'supplier' => $actor->name,
                'utility_type' => $validated['utility_type'],
                'device_price' => $validated['financing_amount'],
                'meter_number' => $validated['meter_number'],
                'meter_brand_model' => $submeter ? $validated['meter_brand_model'] : 'Electricity token',
                'device_serial' => $submeter ? $validated['device_serial'] : null,
                'quotation_attached' => true,
                'terms' => $terms,
                'accepted_at' => now(),
                'status' => 'submitted',
            ]);
            if ($submeter) {
                ApplicationUpfrontPayment::create([
                    'application_id' => $application->id,
                    'amount' => $validated['upfront_amount'],
                    'reference' => 'UPF-'.Str::upper(Str::random(12)),
                    'channel' => $validated['upfront_channel'],
                    'evidence' => $validated['upfront_receipt'],
                    'paid_at' => $validated['upfront_paid_at'],
                    'recorded_by' => $actor->id,
                ]);
            }
            AuditLog::create(['user_id' => $actor->id, 'action' => 'customer.api_onboarded', 'entity' => 'User', 'entity_id' => $customer->id]);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'application.api_submitted', 'entity' => 'LoanApplication', 'entity_id' => $application->id]);

            return [$customer, $application];
        });

        $loan = $product->auto_approve ? $lending->approve($application, $actor, true) : null;
        $application->refresh();

        return response()->json([
            'message' => $loan ? 'Customer onboarded and application approved automatically.' : 'Customer onboarded and application submitted for review.',
            'data' => [
                'customer_id' => $customer->public_id,
                'application_id' => $application->public_id,
                'application_reference' => $application->reference,
                'application_status' => $application->status,
                'approval_mode' => $product->auto_approve ? 'automatic' : 'manual',
                'loan_id' => $loan?->public_id,
                'loan_reference' => $loan?->reference,
            ],
        ], 201);
    }

    public function loanDetails(Request $request)
    {
        $validated = $request->validate([
            'meter_number' => 'nullable|required_without:phone_number|string|max:80',
            'phone_number' => 'nullable|required_without:meter_number|string|max:30',
        ]);

        $loan = $this->loanQuery($request, $validated['meter_number'] ?? null, $validated['phone_number'] ?? null)
            ->with(['customer', 'application.product', 'installments'])
            ->latest('loans.id')
            ->first();

        if (!$loan) {
            return response()->json(['message' => 'No loan was found for the supplied meter or phone number.'], 404);
        }

        return response()->json(['data' => $this->loanPayload($loan)]);
    }

    public function submitRepayment(Request $request, LendingService $lending)
    {
        $request->merge(['receipt' => $request->input('receipt', $request->input('reciept'))]);
        $validated = $request->validate([
            'amount_purchased' => 'required|integer|min:1|max:1000000000',
            'reference' => 'required|string|max:150|unique:payments,reference',
            'receipt' => 'required|string|max:1000',
            'meter_number' => 'required|string|max:80',
            'phone_number' => 'required|string|max:30',
            'collected_amount' => 'required|integer|min:1|max:1000000000',
        ]);

        $loan = $this->loanQuery($request, $validated['meter_number'], $validated['phone_number'])
            ->whereIn('loans.status', ['active', 'overdue'])
            ->latest('loans.id')
            ->first();

        if (!$loan) {
            throw ValidationException::withMessages(['meter_number' => ['No active loan matches this meter number and phone number for your supplier.']]);
        }

        $actor = $this->supplier($request)->user;
        $token = $request->attributes->get('supplier_api_token');
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'reference' => $validated['reference'],
            'purpose' => 'repayment',
            'amount' => $validated['collected_amount'],
            'purchase_amount' => $validated['amount_purchased'],
            'channel' => 'token_purchase',
            'meter_number' => $validated['meter_number'],
            'customer_phone' => $validated['phone_number'],
            'receipt' => $validated['receipt'],
            'status' => 'pending',
            'evidence' => 'Receipt: '.$validated['receipt'],
            'recorded_by' => $actor->id,
            'supplier_api_token_id' => $token->id,
            'paid_at' => now(),
        ]);

        try {
            $lending->confirm($payment, $actor);
        } catch (\Throwable $exception) {
            $payment->delete();
            throw $exception;
        }

        $loan->refresh();

        return response()->json([
            'message' => 'Repayment recorded and applied successfully.',
            'data' => [
                'payment_id' => $payment->public_id ?? $payment->id,
                'reference' => $payment->reference,
                'receipt' => $payment->receipt,
                'status' => 'confirmed',
                'collected_amount' => (int) $payment->amount,
                'outstanding_amount' => $loan->outstanding(),
                'loan_status' => $loan->status,
                'recorded_at' => $payment->paid_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function meterStatus(Request $request)
    {
        $validated = $request->validate(['meter_number' => 'required|string|max:80']);
        $loan = $this->loanQuery($request, $validated['meter_number'], null)
            ->with(['application', 'payments' => fn ($query) => $query->whereNotNull('purchase_amount')->latest('paid_at')])
            ->latest('loans.id')
            ->first();

        if (!$loan) {
            return response()->json(['message' => 'Meter was not found for this supplier.'], 404);
        }

        $purchase = $loan->payments->first();

        return response()->json(['data' => [
            'meter_number' => $loan->application->meter_number,
            'last_purchase_date' => $purchase?->paid_at?->toIso8601String(),
            'last_purchase_amount' => $purchase ? (int) $purchase->purchase_amount : null,
            'status' => $loan->status,
            'installation_status' => $loan->installation_status,
            'disbursement_status' => $loan->disbursement_status,
        ]]);
    }

    private function loanQuery(Request $request, ?string $meterNumber, ?string $phoneNumber)
    {
        $supplierUserIds = $this->supplier($request)->users()->pluck('users.id')->push($this->supplier($request)->user_id)->unique();

        return Loan::query()
            ->whereHas('customer', fn ($query) => $query->whereIn('onboarded_by', $supplierUserIds))
            ->when($meterNumber, fn ($query) => $query->whereHas('application', fn ($application) => $application->where('meter_number', $meterNumber)))
            ->when($phoneNumber, fn ($query) => $query->whereHas('customer', fn ($customer) => $customer->where('phone', $phoneNumber)));
    }

    private function supplier(Request $request): SupplierProfile
    {
        return $request->attributes->get('supplier_profile');
    }

    private function loanPayload(Loan $loan): array
    {
        return [
            'loan_id' => $loan->public_id,
            'reference' => $loan->reference,
            'meter_number' => $loan->application->meter_number,
            'customer' => ['name' => $loan->customer->name, 'phone_number' => $loan->customer->phone],
            'product' => $loan->application->product->name,
            'status' => $loan->status,
            'installation_status' => $loan->installation_status,
            'disbursement_status' => $loan->disbursement_status,
            'principal' => (int) ($loan->terms['principal'] ?? 0),
            'repayment_total' => (int) ($loan->terms['repayment_total'] ?? 0),
            'paid_amount' => (int) $loan->payments()->where('purpose', 'repayment')->where('status', 'confirmed')->sum('amount'),
            'outstanding_amount' => $loan->outstanding(),
            'past_due_amount' => $loan->pastDueAmount(),
            'past_due_days' => $loan->pastDueDays(),
            'schedule' => $loan->installments->map(fn ($item) => [
                'installment' => $item->number,
                'due_date' => $item->due_date->toDateString(),
                'amount' => (int) ($item->principal + $item->charge),
                'paid_amount' => (int) ($item->paid_principal + $item->paid_charge),
                'status' => $item->status,
            ])->values(),
        ];
    }
}
