<?php

namespace Tests\Feature;

use App\Models\{Financier, Installment, Loan, LoanApplication, Product, SupplierApiToken, SupplierProfile, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_api_supports_lookup_repayment_and_meter_status(): void
    {
        $owner = User::create(['name' => 'API Supplier', 'email' => 'api-supplier@example.test', 'password' => 'Password@123', 'role' => 'supplier']);
        $supplier = SupplierProfile::create(['user_id' => $owner->id, 'company_name' => 'API Supplier Ltd', 'phone' => '255700000001', 'active' => true]);
        $supplier->users()->attach($owner->id);
        $plainToken = 'mnaf_test-token';
        SupplierApiToken::create(['supplier_profile_id' => $supplier->id, 'name' => 'Test integration', 'token_hash' => hash('sha256', $plainToken)]);

        $customer = User::create(['name' => 'API Customer', 'email' => 'api-customer@example.test', 'phone' => '255700000002', 'password' => 'Password@123', 'role' => 'customer', 'onboarded_by' => $owner->id]);
        $financier = Financier::create(['name' => 'API Finance', 'active' => true]);
        $product = Product::create(['name' => 'Token financing', 'code' => 'API-TOKEN', 'category' => 'token', 'description' => 'API token loan', 'value' => 5000, 'down_payment' => 0, 'commission' => 0, 'commission_financed' => false, 'principal' => 5000, 'finance_charge' => 1000, 'installments' => 1, 'active' => true]);
        $product->financiers()->attach($financier->id);
        $supplier->products()->attach($product->id);
        $terms = ['category' => 'token', 'principal' => 5000, 'finance_charge' => 1000, 'repayment_total' => 6000, 'upfront' => 0, 'installments' => 1];
        $application = LoanApplication::create(['reference' => 'APP-API-001', 'user_id' => $customer->id, 'product_id' => $product->id, 'financier_id' => $financier->id, 'phone' => $customer->phone, 'id_number' => 'API-ID-001', 'address' => 'Dar es Salaam', 'meter_number' => 'MTR-API-001', 'terms' => $terms, 'accepted_at' => now(), 'status' => 'approved']);
        $loan = Loan::create(['reference' => 'MN-API-001', 'application_id' => $application->id, 'user_id' => $customer->id, 'financier_id' => $financier->id, 'terms' => $terms, 'status' => 'active', 'installation_status' => 'not_required', 'disbursement_status' => 'pending']);
        Installment::create(['loan_id' => $loan->id, 'number' => 1, 'due_date' => now()->addMonth(), 'principal' => 5000, 'charge' => 1000, 'status' => 'pending']);

        $headers = ['Authorization' => 'Bearer '.$plainToken];

        $this->getJson('/api/v1/supplier/loans/details?meter_number=MTR-API-001', $headers)
            ->assertOk()
            ->assertJsonPath('data.reference', 'MN-API-001')
            ->assertJsonPath('data.outstanding_amount', 6000);

        $this->postJson('/api/v1/supplier/repayments', [
            'amount_purchased' => 10000,
            'reference' => 'TOKEN-RECEIPT-001',
            'receipt' => 'Receipt 001',
            'meter_number' => 'MTR-API-001',
            'phone_number' => '255700000002',
            'collected_amount' => 1500,
        ], $headers)->assertCreated()->assertJsonPath('data.outstanding_amount', 4500);

        $this->assertDatabaseHas('payments', ['reference' => 'TOKEN-RECEIPT-001', 'status' => 'confirmed', 'purchase_amount' => 10000, 'amount' => 1500]);

        $this->getJson('/api/v1/supplier/meters/status?meter_number=MTR-API-001', $headers)
            ->assertOk()
            ->assertJsonPath('data.last_purchase_amount', 10000)
            ->assertJsonPath('data.status', 'active');

        $this->getJson('/api/v1/supplier/loans/details?meter_number=MTR-API-001')
            ->assertUnauthorized();

        $fixedKey = 'mnaf_live_test-fixed-key';
        $apiSecret = 'test-signing-secret';
        SupplierApiToken::create(['supplier_profile_id' => $supplier->id, 'name' => 'Signed integration', 'key_id' => $fixedKey, 'token_hash' => hash('sha256', 'unused-token'), 'encrypted_secret' => Crypt::encryptString($apiSecret)]);
        $timestamp = (string) now()->timestamp;
        $canonical = implode("\n", [$timestamp, 'GET', '/api/v1/supplier/loans/details', hash('sha256', '[]')]);
        $signedHeaders = ['X-API-Key' => $fixedKey, 'X-Timestamp' => $timestamp, 'X-Signature' => hash_hmac('sha256', $canonical, $apiSecret)];
        $this->getJson('/api/v1/supplier/loans/details?meter_number=MTR-API-001', $signedHeaders)
            ->assertOk()
            ->assertJsonPath('data.reference', 'MN-API-001');

        $this->actingAs($owner)->get('/api-integration')
            ->assertOk()
            ->assertSee($fixedKey)
            ->assertSee($apiSecret);

        $this->postJson('/api/v1/supplier/customers/onboard', [
            'first_name' => 'New',
            'last_name' => 'Customer',
            'id_type' => 'national_id',
            'id_number' => 'API-ID-002',
            'date_of_birth' => '1995-01-01',
            'gender' => 'female',
            'phone_number' => '255700000003',
            'region' => 'Arusha',
            'district' => 'Arumeru',
            'ward' => 'Akheri',
            'address' => 'API Test Address',
            'occupation' => 'Trader',
            'next_of_kin_name' => 'Kin Customer',
            'next_of_kin_phone' => '255700000004',
            'product_code' => 'API-TOKEN',
            'utility_type' => 'electricity',
            'financing_amount' => 5000,
            'meter_number' => 'MTR-API-002',
            'terms_accepted' => true,
            'quotation_confirmed' => true,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.application_status', 'submitted')
            ->assertJsonPath('data.approval_mode', 'manual');

        $this->assertDatabaseHas('users', ['phone' => '255700000003', 'onboarded_by' => $owner->id]);
        $this->assertDatabaseHas('applications', ['meter_number' => 'MTR-API-002', 'product_id' => $product->id]);
    }
}
