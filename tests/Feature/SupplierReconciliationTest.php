<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Financier;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\SupplierReconciliation;
use App\Models\User;
use App\Services\LendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_can_reconcile_selected_collections_once_and_cannot_transfer_another_suppliers_payments(): void
    {
        [$supplierUser, $supplier] = $this->createSupplier('Collection Supplier', 'collections@example.test');
        [$otherSupplierUser] = $this->createSupplier('Other Supplier', 'other-supplier@example.test');
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'reconciliation-admin@example.test',
            'password' => 'test-password-long',
            'role' => 'admin',
        ]);
        $loan = $this->createLoan($supplier);
        $lending = app(LendingService::class);

        $first = $this->confirmedPayment($loan, $supplierUser, 1000, 'COLLECTION-001', $lending);
        $second = $this->confirmedPayment($loan, $supplierUser, 1500, 'COLLECTION-002', $lending);
        $adminPayment = $this->confirmedPayment($loan, $admin, 500, 'ADMIN-COLLECTION', $lending);

        $this->actingAs($supplierUser)
            ->get('/reconciliations')
            ->assertOk()
            ->assertSee('COLLECTION-001')
            ->assertSee('COLLECTION-002')
            ->assertDontSee('ADMIN-COLLECTION')
            ->assertSee('2,500');

        $this->actingAs($otherSupplierUser)
            ->from('/reconciliations')
            ->post('/reconciliations', [
                'payment_ids' => [$first->id],
                'transfer_reference' => 'BLUETICK-TRANSFER-WRONG',
                'transferred_at' => today()->toDateString(),
            ])
            ->assertRedirect('/reconciliations')
            ->assertSessionHasErrors('payment_ids');

        $this->assertSame(0, SupplierReconciliation::count());
        $this->assertNull($first->fresh()->supplier_reconciliation_id);

        $this->actingAs($supplierUser)
            ->post('/reconciliations', [
                'payment_ids' => [$first->id, $second->id],
                'transfer_reference' => 'BLUETICK-TRANSFER-001',
                'transferred_at' => today()->toDateString(),
            ])
            ->assertRedirect('/reconciliations')
            ->assertSessionHasNoErrors();

        $reconciliation = SupplierReconciliation::firstOrFail();
        $this->assertSame($supplier->id, $reconciliation->supplier_profile_id);
        $this->assertSame(2500, $reconciliation->total_amount);
        $this->assertSame($reconciliation->id, $first->fresh()->supplier_reconciliation_id);
        $this->assertSame($reconciliation->id, $second->fresh()->supplier_reconciliation_id);
        $this->assertNull($adminPayment->fresh()->supplier_reconciliation_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier_reconciliation.booked',
            'entity_id' => $reconciliation->id,
        ]);

        $this->get('/reconciliations')
            ->assertOk()
            ->assertSee('BLUETICK-TRANSFER-001')
            ->assertSee('All collected repayments have been transferred.');
        $this->get('/payments')
            ->assertOk()
            ->assertSee('Transferred')
            ->assertSee('BLUETICK-TRANSFER-001');
        $this->get('/loans/'.$loan->public_id)
            ->assertOk()
            ->assertSee('Transferred')
            ->assertSee('BLUETICK-TRANSFER-001');

        $this->from('/reconciliations')
            ->post('/reconciliations', [
                'payment_ids' => [$first->id],
                'transfer_reference' => 'BLUETICK-TRANSFER-002',
                'transferred_at' => today()->toDateString(),
            ])
            ->assertRedirect('/reconciliations')
            ->assertSessionHasErrors('payment_ids');

        $this->assertSame(1, SupplierReconciliation::count());
        $this->assertSame(1, AuditLog::where('action', 'supplier_reconciliation.booked')->count());
    }

    private function createSupplier(string $company, string $email): array
    {
        $user = User::create([
            'name' => $company.' User',
            'email' => $email,
            'phone' => '2557'.random_int(10000000, 99999999),
            'password' => 'test-password-long',
            'role' => 'supplier',
        ]);
        $supplier = SupplierProfile::create([
            'user_id' => $user->id,
            'company_name' => $company,
            'phone' => $user->phone,
            'active' => true,
        ]);
        $supplier->users()->attach($user->id);

        return [$user, $supplier];
    }

    private function createLoan(SupplierProfile $supplier): Loan
    {
        $customer = User::create([
            'name' => 'Reconciliation Customer',
            'email' => 'reconciliation-customer@example.test',
            'phone' => '255700000099',
            'password' => 'test-password-long',
            'role' => 'customer',
            'onboarded_by' => $supplier->user_id,
        ]);
        $financier = Financier::create(['name' => 'Reconciliation Financier', 'active' => true]);
        $product = Product::create([
            'name' => 'Reconciliation Product',
            'code' => 'RECON-PRODUCT',
            'category' => 'token',
            'description' => 'Product used for reconciliation tests.',
            'value' => 5000,
            'down_payment' => 0,
            'commission' => 0,
            'commission_financed' => false,
            'principal' => 5000,
            'finance_charge' => 1000,
            'installments' => 1,
            'active' => true,
        ]);
        $terms = $product->quote();
        $application = LoanApplication::create([
            'reference' => 'APP-RECON-001',
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'financier_id' => $financier->id,
            'supplier_profile_id' => $supplier->id,
            'phone' => $customer->phone,
            'id_number' => 'RECON-ID-001',
            'address' => 'Dar es Salaam',
            'terms' => $terms,
            'accepted_at' => now(),
            'status' => 'approved',
        ]);
        $loan = Loan::create([
            'reference' => 'MN-RECON-001',
            'application_id' => $application->id,
            'user_id' => $customer->id,
            'financier_id' => $financier->id,
            'supplier_profile_id' => $supplier->id,
            'terms' => $terms,
            'status' => 'active',
            'installation_status' => 'not_required',
            'disbursement_status' => 'paid',
        ]);
        Installment::create([
            'loan_id' => $loan->id,
            'number' => 1,
            'due_date' => today()->addMonth(),
            'principal' => 5000,
            'charge' => 1000,
            'status' => 'pending',
        ]);

        return $loan;
    }

    private function confirmedPayment(Loan $loan, User $actor, int $amount, string $reference, LendingService $lending): Payment
    {
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'reference' => $reference,
            'purpose' => 'repayment',
            'amount' => $amount,
            'channel' => 'mobile_money',
            'status' => 'pending',
            'evidence' => 'Receipt '.$reference,
            'recorded_by' => $actor->id,
            'paid_at' => now(),
        ]);
        $lending->confirm($payment, $actor);

        return $payment->fresh();
    }
}
