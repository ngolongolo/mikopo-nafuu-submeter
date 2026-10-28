<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\SupplierProfile;
use App\Models\SupplierReconciliation;
use App\Services\Visibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierReconciliationController
{
    public function index(Request $request)
    {
        $supplier = $this->supplier($request);
        $eligibleBase = $this->eligiblePayments($request);
        $historyBase = SupplierReconciliation::where('supplier_profile_id', $supplier->id);

        $stats = [
            'Awaiting transfer' => (clone $eligibleBase)->count(),
            'Amount awaiting transfer' => (clone $eligibleBase)->sum('amount'),
            'Transfers booked' => (clone $historyBase)->count(),
            'Amount transferred' => (clone $historyBase)->sum('total_amount'),
        ];

        $payments = (clone $eligibleBase)
            ->with('loan.customer')
            ->latest('paid_at')
            ->paginate(30, ['*'], 'payments')
            ->withQueryString();
        $reconciliations = (clone $historyBase)
            ->with(['creator', 'payments.loan.customer'])
            ->withCount('payments')
            ->latest('transferred_at')
            ->latest('id')
            ->paginate(20, ['*'], 'history')
            ->withQueryString();

        return view('supplier-reconciliations', compact('supplier', 'stats', 'payments', 'reconciliations'));
    }

    public function store(Request $request)
    {
        $request->merge(['transfer_reference' => trim((string) $request->input('transfer_reference'))]);
        $validated = $request->validate([
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'required|integer|distinct|exists:payments,id',
            'transfer_reference' => 'required|string|max:150|unique:supplier_reconciliations,transfer_reference',
            'transferred_at' => 'required|date|before_or_equal:today',
        ]);
        $supplier = $this->supplier($request);
        $supplierUserIds = Visibility::supplierUserIds($request->user());

        $reconciliation = DB::transaction(function () use ($request, $validated, $supplier, $supplierUserIds) {
            $paymentIds = collect($validated['payment_ids'])->map(fn ($id) => (int) $id)->values();
            $payments = Payment::whereIn('id', $paymentIds)->lockForUpdate()->get();
            $visibleLoanIds = Visibility::scope(Loan::query(), $request->user())
                ->whereIn('id', $payments->pluck('loan_id'))
                ->pluck('id');

            $invalid = $payments->count() !== $paymentIds->count()
                || $payments->contains(fn (Payment $payment) =>
                    $payment->purpose !== 'repayment'
                    || $payment->status !== 'confirmed'
                    || $payment->supplier_reconciliation_id !== null
                    || !in_array($payment->recorded_by, $supplierUserIds, true)
                    || !$visibleLoanIds->contains($payment->loan_id)
                );

            if ($invalid) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'One or more selected repayments are unavailable, already transferred, or do not belong to your supplier.',
                ]);
            }

            $reconciliation = SupplierReconciliation::create([
                'public_id' => (string) Str::ulid(),
                'supplier_profile_id' => $supplier->id,
                'transfer_reference' => $validated['transfer_reference'],
                'total_amount' => (int) $payments->sum('amount'),
                'transferred_at' => $validated['transferred_at'],
                'created_by' => $request->user()->id,
            ]);
            Payment::whereIn('id', $paymentIds)->update([
                'supplier_reconciliation_id' => $reconciliation->id,
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'supplier_reconciliation.booked',
                'entity' => 'SupplierReconciliation',
                'entity_id' => $reconciliation->id,
                'metadata' => [
                    'transfer_reference' => $reconciliation->transfer_reference,
                    'payment_count' => $payments->count(),
                    'total_amount' => $reconciliation->total_amount,
                ],
            ]);

            return $reconciliation;
        });

        return redirect('/reconciliations')->with(
            'success',
            'Transfer '.$reconciliation->transfer_reference.' booked for '.number_format($reconciliation->total_amount).' TZS. Selected repayments are now marked transferred.'
        );
    }

    private function supplier(Request $request): SupplierProfile
    {
        $supplier = Visibility::supplierProfile($request->user());
        if (!$supplier) {
            throw ValidationException::withMessages(['supplier' => 'Your user account is not assigned to a supplier profile.']);
        }

        return $supplier;
    }

    private function eligiblePayments(Request $request): Builder
    {
        return Payment::query()
            ->where('purpose', 'repayment')
            ->where('status', 'confirmed')
            ->whereNull('supplier_reconciliation_id')
            ->whereIn('recorded_by', Visibility::supplierUserIds($request->user()))
            ->whereIn('loan_id', Visibility::scope(Loan::query(), $request->user())->select('id'));
    }
}
