<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\AuditLog;
use App\Models\DisbursementBatch;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanAdministrationService
{
    public function canDelete(Loan $loan): bool
    {
        return !$this->hasFinancialHistory($loan);
    }

    public function reassignSupplier(Loan $loan, SupplierProfile $supplier, User $actor, ?string $reason = null): void
    {
        $this->requireAdmin($actor);

        DB::transaction(function () use ($loan, $supplier, $actor, $reason) {
            $application = LoanApplication::lockForUpdate()->findOrFail($loan->application_id);
            $lockedLoan = Loan::with('supplier')->lockForUpdate()->findOrFail($loan->id);
            $lockedLoan->setRelation('application', $application);
            $lockedSupplier = SupplierProfile::lockForUpdate()->findOrFail($supplier->id);

            if (!$lockedSupplier->active) {
                throw ValidationException::withMessages(['supplier_profile_id' => 'Choose an active supplier.']);
            }

            if (!$lockedSupplier->products()->whereKey($lockedLoan->application->product_id)->exists()) {
                throw ValidationException::withMessages(['supplier_profile_id' => 'The selected supplier is not eligible for this loan product.']);
            }

            $oldSupplier = $lockedLoan->supplier;
            $lockedLoan->update(['supplier_profile_id' => $lockedSupplier->id]);
            $lockedLoan->application->update([
                'supplier_profile_id' => $lockedSupplier->id,
                'supplier' => $lockedSupplier->company_name,
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'loan.supplier_changed',
                'entity' => 'Loan',
                'entity_id' => $lockedLoan->id,
                'metadata' => [
                    'reference' => $lockedLoan->reference,
                    'old_supplier_id' => $oldSupplier?->id,
                    'old_supplier' => $oldSupplier?->company_name,
                    'new_supplier_id' => $lockedSupplier->id,
                    'new_supplier' => $lockedSupplier->company_name,
                    'reason' => $reason,
                ],
            ]);
        }, 3);
    }

    public function deleteLoan(Loan $loan, User $actor, string $reason): void
    {
        $this->requireAdmin($actor);

        DB::transaction(function () use ($loan, $actor, $reason) {
            $application = LoanApplication::lockForUpdate()->findOrFail($loan->application_id);
            $lockedLoan = Loan::with('supplier')
                ->lockForUpdate()
                ->findOrFail($loan->id);
            $lockedLoan->setRelation('application', $application);
            $this->lockRelatedRecords($lockedLoan);

            if (!$this->canDelete($lockedLoan)) {
                throw ValidationException::withMessages([
                    'loan' => 'This loan has confirmed financial activity and cannot be deleted. Reverse or resolve the transactions instead.',
                ]);
            }

            $paymentIds = $lockedLoan->payments->pluck('id');
            $batchIds = collect([
                $lockedLoan->batchItem?->disbursement_batch_id,
                $lockedLoan->payout?->disbursement_batch_id,
            ])->filter()->unique()->values();

            $snapshot = [
                'reference' => $lockedLoan->reference,
                'application_id' => $lockedLoan->application_id,
                'customer_id' => $lockedLoan->user_id,
                'financier_id' => $lockedLoan->financier_id,
                'supplier_id' => $lockedLoan->supplier_profile_id,
                'supplier' => $lockedLoan->supplier?->company_name,
                'status' => $lockedLoan->status,
                'principal' => (int) ($lockedLoan->terms['principal'] ?? 0),
                'repayment_total' => (int) ($lockedLoan->terms['repayment_total'] ?? 0),
                'payment_count' => $paymentIds->count(),
                'installment_count' => $lockedLoan->installments->count(),
                'reason' => $reason,
            ];

            $allocationIds = Allocation::whereIn('payment_id', $paymentIds)->pluck('id');
            Allocation::whereIn('reversal_of', $allocationIds)->update(['reversal_of' => null]);
            Allocation::whereIn('payment_id', $paymentIds)->update(['reversal_of' => null]);
            Allocation::whereIn('payment_id', $paymentIds)->delete();
            Payment::whereIn('id', $paymentIds)->delete();
            Installment::where('loan_id', $lockedLoan->id)->delete();
            $lockedLoan->disbursement()->delete();
            $lockedLoan->batchItem()->delete();
            $lockedLoan->payout()->delete();

            $loanId = $lockedLoan->id;
            $lockedLoan->delete();

            $application->update([
                'status' => 'submitted',
                'reviewed_by' => null,
                'decision_reason' => null,
            ]);

            foreach ($batchIds as $batchId) {
                $batch = DisbursementBatch::lockForUpdate()->find($batchId);
                if (!$batch) {
                    continue;
                }

                $total = (int) $batch->items()->sum('amount');
                if ($batch->status === 'unpaid' && $total === 0) {
                    $batch->delete();
                } else {
                    $batch->update(['total_amount' => $total]);
                }
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'loan.deleted',
                'entity' => 'Loan',
                'entity_id' => $loanId,
                'metadata' => $snapshot,
            ]);
        }, 3);
    }

    private function hasFinancialHistory(Loan $loan): bool
    {
        $payments = $loan->relationLoaded('payments') ? $loan->payments : $loan->payments()->get();
        $installments = $loan->relationLoaded('installments') ? $loan->installments : $loan->installments()->get();
        $disbursement = $loan->relationLoaded('disbursement') ? $loan->disbursement : $loan->disbursement()->first();
        $payout = $loan->relationLoaded('payout') ? $loan->payout : $loan->payout()->with('batch')->first();
        $batchItem = $loan->relationLoaded('batchItem') ? $loan->batchItem : $loan->batchItem()->with('batch')->first();

        return $loan->disbursement_status === 'paid'
            || $loan->disbursed_at !== null
            || $disbursement !== null
            || $payments->contains(fn ($payment) => in_array($payment->status, ['confirmed', 'reversed'], true))
            || $installments->contains(fn ($installment) => $installment->paid_principal > 0 || $installment->paid_charge > 0)
            || ($payout && ($payout->status === 'paid' || $payout->platform_commission_status === 'booked'))
            || ($payout?->batch?->status === 'paid')
            || ($batchItem?->batch?->status === 'paid');
    }

    private function lockRelatedRecords(Loan $loan): void
    {
        $loan->setRelation('payments', $loan->payments()->lockForUpdate()->get());
        $loan->setRelation('installments', $loan->installments()->lockForUpdate()->get());
        $loan->setRelation('disbursement', $loan->disbursement()->lockForUpdate()->first());

        $payout = $loan->payout()->lockForUpdate()->first();
        $batchItem = $loan->batchItem()->lockForUpdate()->first();
        $batchIds = collect([$payout?->disbursement_batch_id, $batchItem?->disbursement_batch_id])->filter()->unique();
        $batches = DisbursementBatch::whereIn('id', $batchIds)->lockForUpdate()->get()->keyBy('id');

        if ($payout) {
            $payout->setRelation('batch', $batches->get($payout->disbursement_batch_id));
        }
        if ($batchItem) {
            $batchItem->setRelation('batch', $batches->get($batchItem->disbursement_batch_id));
        }

        $loan->setRelation('payout', $payout);
        $loan->setRelation('batchItem', $batchItem);
    }

    private function requireAdmin(User $actor): void
    {
        if ($actor->role !== 'admin') {
            throw ValidationException::withMessages(['authorization' => 'Only system administrators may manage loan assignments or deletion.']);
        }
    }
}
