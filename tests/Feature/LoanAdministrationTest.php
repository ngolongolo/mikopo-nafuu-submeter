<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchItem;
use App\Models\Financier;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SupplierApiToken;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\LendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private Financier $financier;
    private Product $product;
    private LendingService $lending;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::create([
            'name' => 'System Admin',
            'email' => 'loan-admin@example.test',
            'password' => 'test-password-long',
            'role' => 'admin',
        ]);
        $this->customer = User::create([
            'name' => 'Loan Customer',
            'email' => 'loan-customer@example.test',
            'password' => 'test-password-long',
            'role' => 'customer',
        ]);
        $this->financier = Financier::create([
            'name' => 'Administration Test MFI',
            'active' => true,
        ]);
        $this->product = Product::where('code', 'SUBMETER')->firstOrFail();
        $this->product->update(['active' => true]);
        $this->financier->products()->syncWithoutDetaching([$this->product->id]);
        $this->lending = app(LendingService::class);
    }

    public function test_only_admin_can_reassign_one_loan_and_supplier_visibility_moves_with_it(): void
    {
        [$oldSupplierUser, $oldSupplier] = $this->createSupplier('Original Supplier', true);
        [$newSupplierUser, $newSupplier] = $this->createSupplier('Replacement Supplier', true);
        $targetLoan = $this->createLoan($oldSupplier);
        $otherLoan = $this->createLoan($oldSupplier);

        $this->actingAs($oldSupplierUser)
            ->put('/loans/'.$targetLoan->public_id.'/supplier', [
                'supplier_profile_id' => $newSupplier->id,
                'reason' => 'Unauthorized attempt',
            ])
            ->assertForbidden();

        $this->assertSame($oldSupplier->id, $targetLoan->fresh()->supplier_profile_id);

        $this->actingAs($this->admin)
            ->put('/loans/'.$targetLoan->public_id.'/supplier', [
                'supplier_profile_id' => $newSupplier->id,
                'reason' => 'Corrected supplier assignment',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($newSupplier->id, $targetLoan->fresh()->supplier_profile_id);
        $this->assertSame($oldSupplier->id, $otherLoan->fresh()->supplier_profile_id);
        $this->assertSame($newSupplier->id, $targetLoan->application->fresh()->supplier_profile_id);
        $this->assertSame('Replacement Supplier', $targetLoan->application->fresh()->supplier);

        $audit = AuditLog::where('action', 'loan.supplier_changed')
            ->where('entity_id', $targetLoan->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame($oldSupplier->id, $audit->metadata['old_supplier_id']);
        $this->assertSame($newSupplier->id, $audit->metadata['new_supplier_id']);
        $this->assertSame('Corrected supplier assignment', $audit->metadata['reason']);

        $this->actingAs($oldSupplierUser)
            ->get('/loans/'.$targetLoan->public_id)
            ->assertForbidden();
        $this->get('/loans/'.$otherLoan->public_id)->assertOk();
        $this->get('/loans')
            ->assertOk()
            ->assertDontSee($targetLoan->reference)
            ->assertSee($otherLoan->reference);
        $this->get('/applications')
            ->assertOk()
            ->assertDontSee($targetLoan->application->reference)
            ->assertSee($otherLoan->application->reference);
        $this->get('/payouts')
            ->assertOk()
            ->assertDontSee($targetLoan->reference)
            ->assertSee($otherLoan->reference);
        $this->get('/customers/'.$this->customer->public_id)
            ->assertOk()
            ->assertDontSee($targetLoan->application->reference)
            ->assertSee($otherLoan->application->reference);

        $this->actingAs($newSupplierUser)
            ->get('/loans/'.$targetLoan->public_id)
            ->assertOk()
            ->assertSee($targetLoan->reference);
        $this->get('/loans/'.$otherLoan->public_id)->assertForbidden();
        $this->get('/loans')
            ->assertOk()
            ->assertSee($targetLoan->reference)
            ->assertDontSee($otherLoan->reference);
        $this->get('/applications')
            ->assertOk()
            ->assertSee($targetLoan->application->reference)
            ->assertDontSee($otherLoan->application->reference);
        $this->get('/payouts')
            ->assertOk()
            ->assertSee($targetLoan->reference)
            ->assertDontSee($otherLoan->reference);
        $this->get('/customers')
            ->assertOk()
            ->assertSee($this->customer->name);
        $this->get('/customers/'.$this->customer->public_id)
            ->assertOk()
            ->assertSee($targetLoan->application->reference)
            ->assertDontSee($otherLoan->application->reference);

        $oldToken = 'old-supplier-token';
        $newToken = 'new-supplier-token';
        SupplierApiToken::create([
            'supplier_profile_id' => $oldSupplier->id,
            'name' => 'Old supplier test key',
            'token_hash' => hash('sha256', $oldToken),
        ]);
        SupplierApiToken::create([
            'supplier_profile_id' => $newSupplier->id,
            'name' => 'New supplier test key',
            'token_hash' => hash('sha256', $newToken),
        ]);
        $lookup = '/api/v1/supplier/loans/details?meter_number='.$targetLoan->application->meter_number;
        $this->withToken($oldToken)->getJson($lookup)->assertNotFound();
        $this->withToken($newToken)->getJson($lookup)
            ->assertOk()
            ->assertJsonPath('data.reference', $targetLoan->reference);
    }

    public function test_admin_cannot_assign_an_invalid_inactive_or_product_ineligible_supplier(): void
    {
        [, $originalSupplier] = $this->createSupplier('Original Supplier', true);
        [, $ineligibleSupplier] = $this->createSupplier('Ineligible Supplier', false);
        [, $inactiveSupplier] = $this->createSupplier('Inactive Supplier', true, false);
        $loan = $this->createLoan($originalSupplier);
        $url = '/loans/'.$loan->public_id.'/supplier';

        $this->actingAs($this->admin)
            ->from('/loans/'.$loan->public_id)
            ->put($url, ['supplier_profile_id' => SupplierProfile::max('id') + 999])
            ->assertRedirect('/loans/'.$loan->public_id)
            ->assertSessionHasErrors('supplier_profile_id');

        $this->from('/loans/'.$loan->public_id)
            ->put($url, ['supplier_profile_id' => $ineligibleSupplier->id])
            ->assertRedirect('/loans/'.$loan->public_id)
            ->assertSessionHasErrors('supplier_profile_id');

        $this->from('/loans/'.$loan->public_id)
            ->put($url, ['supplier_profile_id' => $inactiveSupplier->id])
            ->assertRedirect('/loans/'.$loan->public_id)
            ->assertSessionHasErrors('supplier_profile_id');

        $this->assertSame($originalSupplier->id, $loan->fresh()->supplier_profile_id);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'loan.supplier_changed',
            'entity_id' => $loan->id,
        ]);
    }

    public function test_admin_can_delete_safe_loan_and_cleanup_dependencies_reset_application_and_audit(): void
    {
        [, $supplier] = $this->createSupplier('Deletable Supplier', true);
        [$replacementSupplierUser, $replacementSupplier] = $this->createSupplier('Persistent Replacement Supplier', true);
        $loan = $this->createLoan($supplier);
        $this->actingAs($this->admin)->put('/loans/'.$loan->public_id.'/supplier', [
            'supplier_profile_id' => $replacementSupplier->id,
            'reason' => 'Use the replacement supplier if the application is approved again',
        ])->assertSessionHasNoErrors();
        $application = $loan->application;
        $loanId = $loan->id;
        $reference = $loan->reference;
        $payoutId = $loan->payout()->value('id');
        $batch = DisbursementBatch::create([
            'reference' => 'DELETE-BATCH-'.Str::upper(Str::random(6)),
            'batch_date' => today(),
            'status' => 'unpaid',
            'total_amount' => (int) $loan->terms['principal'],
            'created_by' => $this->admin->id,
        ]);
        $batchItem = DisbursementBatchItem::create([
            'disbursement_batch_id' => $batch->id,
            'loan_id' => $loan->id,
            'amount' => (int) $loan->terms['principal'],
            'device_serial' => 'DELETE-TEST-SERIAL',
        ]);

        $pendingPayment = Payment::create([
            'loan_id' => $loan->id,
            'reference' => 'PENDING-DELETE-'.Str::upper(Str::random(6)),
            'purpose' => 'repayment',
            'amount' => 1000,
            'channel' => 'bank',
            'status' => 'pending',
            'evidence' => 'Pending receipt',
            'recorded_by' => $this->admin->id,
            'paid_at' => now(),
        ]);

        $this->assertGreaterThan(0, $loan->installments()->count());

        $this->actingAs($this->admin)
            ->from('/loans/'.$loan->public_id)
            ->delete('/loans/'.$loan->public_id, [
                'confirmation' => 'WRONG-REFERENCE',
                'reason' => 'Duplicate loan entered during onboarding',
            ])
            ->assertRedirect('/loans/'.$loan->public_id)
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('loans', ['id' => $loanId]);

        $this->actingAs($this->admin)
            ->delete('/loans/'.$loan->public_id, [
                'confirmation' => $reference,
                'reason' => 'Duplicate loan entered during onboarding',
            ])
            ->assertRedirect('/loans')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('loans', ['id' => $loanId]);
        $this->assertDatabaseMissing('installments', ['loan_id' => $loanId]);
        $this->assertDatabaseMissing('payments', ['id' => $pendingPayment->id]);
        $this->assertDatabaseMissing('payouts', ['id' => $payoutId]);
        $this->assertDatabaseMissing('disbursement_batch_items', ['id' => $batchItem->id]);
        $this->assertDatabaseMissing('disbursement_batches', ['id' => $batch->id]);
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'submitted',
            'supplier_profile_id' => $replacementSupplier->id,
            'reviewed_by' => null,
            'decision_reason' => null,
        ]);

        $audit = AuditLog::where('action', 'loan.deleted')
            ->where('entity_id', $loanId)
            ->firstOrFail();

        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame($reference, $audit->metadata['reference']);
        $this->assertSame('Duplicate loan entered during onboarding', $audit->metadata['reason']);
        $this->assertSame(1, $audit->metadata['payment_count']);

        $this->actingAs($replacementSupplierUser)
            ->get('/customers/'.$this->customer->public_id)
            ->assertOk()
            ->assertSee($application->reference);

        $replacement = $this->lending->approve($application->fresh(), $this->admin);
        $this->assertNotSame($loanId, $replacement->id);
        $this->assertSame($replacementSupplier->id, $replacement->supplier_profile_id);
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_deletion_is_blocked_after_confirmed_financial_activity(): void
    {
        [, $supplier] = $this->createSupplier('Protected Supplier', true);
        $loan = $this->createLoan($supplier);
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'reference' => 'CONFIRMED-'.Str::upper(Str::random(6)),
            'purpose' => 'repayment',
            'amount' => 1000,
            'channel' => 'bank',
            'status' => 'pending',
            'evidence' => 'Verified receipt',
            'recorded_by' => $this->admin->id,
            'paid_at' => now(),
        ]);
        $this->lending->confirm($payment, $this->admin);
        $allocationCount = $payment->allocations()->count();

        $this->actingAs($this->admin)
            ->from('/loans/'.$loan->public_id)
            ->delete('/loans/'.$loan->public_id, [
                'confirmation' => $loan->reference,
                'reason' => 'Attempt to remove settled activity',
            ])
            ->assertRedirect('/loans/'.$loan->public_id)
            ->assertSessionHasErrors('loan');

        $this->assertDatabaseHas('loans', ['id' => $loan->id]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'confirmed',
        ]);
        $this->assertGreaterThan(0, $allocationCount);
        $this->assertSame($allocationCount, $payment->allocations()->count());
        $this->assertSame('approved', $loan->application->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'loan.deleted',
            'entity_id' => $loan->id,
        ]);
    }

    public function test_loan_administration_controls_are_visible_only_to_admins(): void
    {
        [$supplierUser, $supplier] = $this->createSupplier('Visible Supplier', true);
        $loan = $this->createLoan($supplier);

        $this->actingAs($this->admin)
            ->get('/loans/'.$loan->public_id)
            ->assertOk()
            ->assertSee('Change loan supplier')
            ->assertSee('Delete loan');

        $this->actingAs($supplierUser)
            ->get('/loans/'.$loan->public_id)
            ->assertOk()
            ->assertDontSee('Change loan supplier')
            ->assertDontSee('Delete loan');

        $this->delete('/loans/'.$loan->public_id, [
            'confirmation' => $loan->reference,
            'reason' => 'Unauthorized delete attempt',
        ])->assertForbidden();

        $this->assertDatabaseHas('loans', ['id' => $loan->id]);
    }

    private function createSupplier(
        string $companyName,
        bool $eligible,
        bool $active = true,
    ): array {
        $user = User::create([
            'name' => $companyName.' User',
            'email' => Str::slug($companyName).'-'.Str::lower(Str::random(6)).'@example.test',
            'phone' => '2557'.random_int(10000000, 99999999),
            'password' => 'test-password-long',
            'role' => 'supplier',
        ]);
        $supplier = SupplierProfile::create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'phone' => $user->phone,
            'active' => $active,
        ]);
        $supplier->users()->attach($user->id);

        if ($eligible) {
            $supplier->products()->attach($this->product->id);
        }

        return [$user, $supplier];
    }

    private function createLoan(SupplierProfile $supplier): Loan
    {
        $this->customer->update(['onboarded_by' => $supplier->user_id]);
        $application = LoanApplication::create([
            'reference' => 'APP-ADMIN-'.Str::upper(Str::random(8)),
            'user_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'financier_id' => $this->financier->id,
            'phone' => '255700000000',
            'id_number' => 'ADMIN-'.Str::upper(Str::random(8)),
            'address' => 'Dar es Salaam',
            'supplier' => $supplier->company_name,
            'meter_number' => 'METER-'.Str::upper(Str::random(8)),
            'terms' => $this->product->quote(),
            'accepted_at' => now(),
        ]);

        return $this->lending->approve($application, $this->admin);
    }
}
