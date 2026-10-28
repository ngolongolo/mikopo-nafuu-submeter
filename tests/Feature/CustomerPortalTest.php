<?php

namespace Tests\Feature;

use App\Models\CustomerLead;
use App\Models\Financier;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_uses_sms_code_to_track_application_loan_and_repayments(): void
    {
        config([
            'services.sprint_sms.api_id' => 'test-id',
            'services.sprint_sms.api_password' => 'test-password',
        ]);
        Http::fake(['api.sprintsmsservice.com/*' => Http::response(['status' => 'success'])]);

        $financier = Financier::create(['name' => 'Portal Financier', 'active' => true]);
        $product = Product::create([
            'name' => 'Portal submeter',
            'code' => 'PORTAL-METER',
            'category' => 'submeter',
            'description' => 'Portal test meter',
            'value' => 100000,
            'down_payment' => 40000,
            'commission' => 5000,
            'commission_financed' => false,
            'principal' => 60000,
            'finance_charge' => 15000,
            'installments' => 3,
            'active' => true,
        ]);
        $terms = $product->quote();
        $customer = User::create([
            'name' => 'Portal Customer',
            'email' => 'portal-customer@example.test',
            'phone' => '0744000400',
            'password' => Str::random(32),
            'role' => 'customer',
        ]);
        $admin = User::create([
            'name' => 'Portal Admin',
            'email' => 'portal-admin@example.test',
            'phone' => '0744000499',
            'password' => Str::random(32),
            'role' => 'admin',
        ]);
        $application = LoanApplication::create([
            'reference' => 'APP-PORTAL-001',
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'financier_id' => $financier->id,
            'phone' => $customer->phone,
            'id_number' => 'PORTAL-ID-001',
            'address' => 'Dar es Salaam',
            'meter_number' => 'METER-PORTAL-001',
            'terms' => $terms,
            'accepted_at' => now(),
            'status' => 'approved',
        ]);
        $loan = Loan::create([
            'reference' => 'LOAN-PORTAL-001',
            'application_id' => $application->id,
            'user_id' => $customer->id,
            'financier_id' => $financier->id,
            'terms' => $terms,
            'status' => 'active',
        ]);
        Installment::create([
            'loan_id' => $loan->id,
            'number' => 1,
            'due_date' => today()->addMonth(),
            'principal' => 20000,
            'charge' => 5000,
            'paid_principal' => 8000,
            'paid_charge' => 2000,
            'status' => 'partial',
        ]);
        Payment::create([
            'loan_id' => $loan->id,
            'reference' => 'PAY-PORTAL-001',
            'purpose' => 'repayment',
            'amount' => 10000,
            'channel' => 'mobile_money',
            'status' => 'confirmed',
            'evidence' => 'Customer receipt',
            'recorded_by' => $admin->id,
            'confirmed_by' => $admin->id,
            'paid_at' => now(),
            'confirmed_at' => now(),
        ]);
        $lead = CustomerLead::create([
            'public_id' => (string) Str::ulid(),
            'reference' => 'LEAD-PORTAL-001',
            'product_id' => $product->id,
            'request_type' => 'submeter',
            'first_name' => 'Portal',
            'last_name' => 'Customer',
            'phone' => $customer->phone,
            'phone_normalized' => '255744000400',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga East',
            'address' => 'Portal Street',
            'utility_type' => 'electricity',
            'meter_quantity' => 1,
            'financing_amount' => 100000,
            'terms' => $terms,
            'terms_accepted_at' => now(),
            'confirmed_at' => now(),
            'status' => 'onboarded',
            'application_id' => $application->id,
            'onboarded_at' => now(),
        ]);

        $this->get('/track')->assertOk()->assertSee('Customer sign in');
        $this->post('/track/code', ['phone' => '0744 000 400'])
            ->assertRedirect('/track/verify')
            ->assertSessionHasNoErrors();
        $this->get('/track/verify')->assertOk()->assertSee('255744•••400');

        $message = Http::recorded()[0][0]['textmessage'];
        preg_match('/\b(\d{6})\b/', $message, $matches);
        $this->post('/track/verify', ['code' => $matches[1]])
            ->assertRedirect('/track/dashboard')
            ->assertSessionHasNoErrors();

        $this->get('/track/dashboard')
            ->assertOk()
            ->assertSee($lead->reference)
            ->assertSee($application->reference)
            ->assertSee($loan->reference)
            ->assertSee('PAY-PORTAL-001')
            ->assertSee('15,000')
            ->assertSee('10,000');
    }

    public function test_tracker_requires_verified_session(): void
    {
        $this->get('/track/dashboard')->assertRedirect('/track');
    }
}
