<?php

namespace Tests\Feature;

use App\Models\ApplicationUpfrontPayment;
use App\Models\CustomerLead;
use App\Models\Financier;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_customer_requests_financing_and_supplier_accepts_and_onboards_the_lead(): void
    {
        $this->fakeSms();
        [$supplierUser, $supplier, $product] = $this->marketplace();
        [$ineligibleUser] = $this->supplier('Ineligible Supplier', 'ineligible@example.test', '255711111112');

        $this->get('/')
            ->assertOk()
            ->assertSee('Get your meter or electricity token today')
            ->assertSee($product->name)
            ->assertSee('YOUR SUMMARY')
            ->assertDontSee('Amount financed')
            ->assertDontSee('Finance charge')
            ->assertDontSee('Total repayments');

        $payload = [
            'product_id' => $product->id,
            'first_name' => 'Lead',
            'middle_name' => 'Request',
            'last_name' => 'Customer',
            'phone' => '0744000100',
            'email' => 'lead-customer@example.test',
            'region' => 'Arusha',
            'district' => 'Arumeru',
            'ward' => 'Akheri',
            'address' => 'House 12, Akheri',
            'utility_type' => 'electricity',
            'meter_quantity' => 1,
            'financing_amount' => 100000,
            'terms_fingerprint' => hash('sha256', json_encode($product->quote())),
            'accepted' => 1,
        ];

        $response = $this->post('/requests', $payload);
        $lead = CustomerLead::firstOrFail();
        $response->assertRedirect('/requests/'.$lead->public_id);
        $this->assertSame('unconfirmed', $lead->status);
        $this->assertNull($lead->confirmed_at);
        $this->assertSame(45000, $lead->terms['upfront']);
        $this->assertSame(75000, $lead->terms['repayment_total']);
        $this->assertNotNull($lead->terms_accepted_at);

        $this->get('/requests/'.$lead->public_id)
            ->assertOk()
            ->assertSee($lead->reference)
            ->assertSee('Confirmation required')
            ->assertSee('Enter the SMS code')
            ->assertSee('REQUEST SNAPSHOT')
            ->assertSee('What happens next')
            ->assertDontSee($lead->phone)
            ->assertDontSee($lead->email);

        $this->actingAs($supplierUser)
            ->get('/customer-leads')
            ->assertOk()
            ->assertDontSee($lead->reference);

        $code = $this->latestSmsCode();
        $this->post('/requests/'.$lead->public_id.'/confirm', ['code' => $code])
            ->assertRedirect('/requests/'.$lead->public_id)
            ->assertSessionHasNoErrors();
        $lead->refresh();
        $this->assertSame('new', $lead->status);
        $this->assertNotNull($lead->confirmed_at);

        $this->actingAs($ineligibleUser)
            ->get('/customer-leads')
            ->assertOk()
            ->assertDontSee($lead->reference);
        $this->post('/customer-leads/'.$lead->public_id.'/accept')->assertForbidden();

        $this->actingAs($supplierUser)
            ->get('/customer-leads')
            ->assertOk()
            ->assertSee($lead->reference)
            ->assertSee('Accept & onboard customer', false);
        $this->post('/customer-leads/'.$lead->public_id.'/accept')
            ->assertRedirect('/customers/onboard?lead='.$lead->public_id)
            ->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertSame('accepted', $lead->status);
        $this->assertSame($supplier->id, $lead->supplier_profile_id);
        $this->assertSame($supplierUser->id, $lead->claimed_by);

        $this->get('/customers/onboard?lead='.$lead->public_id)
            ->assertOk()
            ->assertSee($lead->reference)
            ->assertSee('255744000100')
            ->assertSee('lead-customer@example.test');

        $this->post('/customers', [
            'customer_lead_id' => $lead->id,
            'first_name' => 'Lead',
            'middle_name' => 'Request',
            'last_name' => 'Customer',
            'id_type' => 'national_id',
            'id_number' => '19900101123450000001',
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
            'phone' => '0744000100',
            'email' => 'lead-customer@example.test',
            'region' => 'Arusha',
            'district' => 'Arumeru',
            'ward' => 'Akheri',
            'address' => 'House 12, Akheri',
            'occupation' => 'Shop owner',
            'next_of_kin' => 'Next Kin',
            'next_of_kin_phone' => '0744000101',
            'product_id' => $product->id,
            'utility_type' => 'electricity',
            'device_price' => 100000,
            'meter_number' => 'LEAD-METER-001',
            'device_serial' => 'LEAD-SERIAL-001',
            'meter_brand_model' => 'LIPACHAP',
            'installation_date' => today()->toDateString(),
            'quotation_attached' => 1,
            'accepted' => 1,
            'upfront_amount' => 45000,
            'upfront_channel' => 'mobile_money',
            'upfront_paid_at' => today()->toDateString(),
            'upfront_evidence' => 'Mobile-money receipt LEAD-UPFRONT-001',
        ])->assertRedirect('/applications')->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertSame('onboarded', $lead->status);
        $this->assertNotNull($lead->application_id);
        $this->assertNotNull($lead->onboarded_at);
        $this->assertDatabaseHas('users', [
            'phone' => '255744000100',
            'next_of_kin_phone' => '255744000101',
            'onboarded_by' => $supplierUser->id,
        ]);
        $this->assertDatabaseHas('applications', [
            'id' => $lead->application_id,
            'supplier_profile_id' => $supplier->id,
            'product_id' => $product->id,
            'phone' => '255744000100',
        ]);
        $this->assertSame(45000, (int) ApplicationUpfrontPayment::where('application_id', $lead->application_id)->value('amount'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_lead.onboarded',
            'entity_id' => $lead->id,
        ]);
    }

    public function test_public_request_requires_terms_and_rejects_stale_pricing(): void
    {
        [, , $product] = $this->marketplace();
        $payload = [
            'product_id' => $product->id,
            'first_name' => 'Terms',
            'last_name' => 'Customer',
            'phone' => '0744000200',
            'region' => 'Arusha',
            'district' => 'Arumeru',
            'ward' => 'Akheri',
            'address' => 'Arusha',
            'utility_type' => 'electricity',
            'meter_quantity' => 1,
            'financing_amount' => 100000,
            'terms_fingerprint' => str_repeat('0', 64),
        ];

        $this->from('/')->post('/requests', $payload)
            ->assertRedirect('/')
            ->assertSessionHasErrors('accepted');
        $this->assertSame(0, CustomerLead::count());

        $payload['accepted'] = 1;
        $this->from('/')->post('/requests', $payload)
            ->assertRedirect('/')
            ->assertSessionHasErrors('accepted');
        $this->assertSame(0, CustomerLead::count());
    }

    public function test_meter_quantity_is_limited_scaled_and_same_kind_cannot_be_submitted_twice(): void
    {
        $this->fakeSms();
        [, , $product] = $this->marketplace();
        $payload = [
            'product_id' => $product->id,
            'first_name' => 'Multi',
            'last_name' => 'Meter',
            'phone' => '0744000300',
            'region' => 'Arusha',
            'district' => 'Arumeru',
            'ward' => 'Akheri',
            'address' => 'Arusha',
            'utility_type' => 'electricity',
            'meter_quantity' => 5,
            'financing_amount' => 500000,
            'terms_fingerprint' => hash('sha256', json_encode($product->quote())),
            'accepted' => 1,
        ];

        $this->post('/requests', $payload)->assertRedirect();
        $lead = CustomerLead::firstOrFail();
        $this->assertSame(5, $lead->meter_quantity);
        $this->assertSame(500000, $lead->financing_amount);
        $this->assertSame(225000, $lead->terms['upfront']);
        $this->assertSame(375000, $lead->terms['repayment_total']);

        $this->from('/')->post('/requests', $payload)
            ->assertRedirect('/')
            ->assertSessionHasErrors('product_id');
        $this->assertSame(1, CustomerLead::count());

        $payload['phone'] = '0744000301';
        $payload['meter_quantity'] = 6;
        $payload['financing_amount'] = 600000;
        $this->from('/')->post('/requests', $payload)
            ->assertRedirect('/')
            ->assertSessionHasErrors('meter_quantity');
    }

    public function test_token_request_only_needs_meter_and_contact_then_supplier_verifies_eligibility(): void
    {
        $this->fakeSms();
        [$supplierUser, $supplier] = $this->supplier('Token Supplier', 'token-supplier@example.test', '255711111115');
        $financier = Financier::create(['name' => 'Token Financier', 'active' => true]);
        $product = Product::create([
            'name' => 'Electricity token financing',
            'code' => 'LEAD-TOKEN',
            'category' => 'token',
            'description' => 'Finance an electricity token for an existing eligible meter.',
            'value' => 5000,
            'down_payment' => 0,
            'commission' => 0,
            'commission_financed' => false,
            'principal' => 5000,
            'finance_charge' => 500,
            'installments' => 1,
            'active' => true,
            'auto_approve' => false,
            'max_loans_per_customer' => 1,
        ]);
        $product->financiers()->attach($financier->id);
        $supplier->products()->attach($product->id);

        $this->get('/')
            ->assertOk()
            ->assertSee('Enter your electricity meter')
            ->assertSee('The supplier will check that this meter exists in their database');

        $this->post('/requests', [
            'product_id' => $product->id,
            'meter_number' => ' 0412 345 678 ',
            'phone' => '0744000400',
            'financing_amount' => 5000,
            'terms_fingerprint' => hash('sha256', json_encode($product->quote())),
            'accepted' => 1,
        ])->assertRedirect();

        $lead = CustomerLead::firstOrFail();
        $this->assertSame('0412345678', $lead->meter_number);
        $this->assertSame('255744000400', $lead->phone);
        $this->assertNull($lead->first_name);
        $this->assertNull($lead->region);
        $this->assertSame('electricity', $lead->utility_type);

        $this->post('/requests/'.$lead->public_id.'/confirm', ['code' => $this->latestSmsCode()])
            ->assertRedirect('/requests/'.$lead->public_id)
            ->assertSessionHasNoErrors();

        $this->actingAs($supplierUser)
            ->get('/customer-leads')
            ->assertOk()
            ->assertSee('Verify meter '.$lead->meter_number)
            ->assertSee('Meter exists in our database')
            ->assertSee('Meter is eligible for financing');

        $this->from('/customer-leads')
            ->post('/customer-leads/'.$lead->public_id.'/accept')
            ->assertRedirect('/customer-leads')
            ->assertSessionHasErrors(['meter_exists', 'meter_eligible']);
        $this->assertSame('new', $lead->refresh()->status);

        $this->post('/customer-leads/'.$lead->public_id.'/accept', [
            'meter_exists' => 1,
            'meter_eligible' => 1,
            'meter_verification_note' => 'Matched active prepaid meter account.',
        ])->assertRedirect('/customers/onboard?lead='.$lead->public_id)
            ->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertSame('accepted', $lead->status);
        $this->assertTrue($lead->meter_exists);
        $this->assertTrue($lead->meter_eligible);
        $this->assertSame($supplierUser->id, $lead->meter_verified_by);
        $this->assertNotNull($lead->meter_verified_at);

        $this->get('/customers/onboard?lead='.$lead->public_id)
            ->assertOk()
            ->assertSee($lead->meter_number);

        $this->from('/customers/onboard?lead='.$lead->public_id)
            ->post('/customers', [
                'customer_lead_id' => $lead->id,
                'meter_number' => 'DIFFERENT-METER',
            ])->assertRedirect('/customers/onboard?lead='.$lead->public_id)
            ->assertSessionHasErrors('meter_number');
    }

    private function marketplace(): array
    {
        [$supplierUser, $supplier] = $this->supplier('Meter Supplier', 'meter-supplier@example.test', '255711111111');
        $financier = Financier::create(['name' => 'Lead Financier', 'active' => true]);
        $product = Product::create([
            'name' => 'Home submeter financing',
            'code' => 'LEAD-SUBMETER',
            'category' => 'submeter',
            'description' => 'A reliable prepaid submeter for homes and small businesses.',
            'value' => 100000,
            'down_payment' => 40000,
            'commission' => 5000,
            'commission_financed' => false,
            'principal' => 60000,
            'finance_charge' => 15000,
            'installments' => 3,
            'active' => true,
            'auto_approve' => false,
            'max_loans_per_customer' => 1,
        ]);
        $product->financiers()->attach($financier->id);
        $supplier->products()->attach($product->id);

        return [$supplierUser, $supplier, $product];
    }

    private function supplier(string $company, string $email, string $phone): array
    {
        $user = User::create([
            'name' => $company.' User',
            'email' => $email,
            'phone' => $phone,
            'password' => 'test-password-long',
            'role' => 'supplier',
        ]);
        $supplier = SupplierProfile::create([
            'user_id' => $user->id,
            'company_name' => $company,
            'phone' => $phone,
            'active' => true,
        ]);
        $supplier->users()->attach($user->id);

        return [$user, $supplier];
    }

    private function fakeSms(): void
    {
        config([
            'services.sprint_sms.api_id' => 'test-id',
            'services.sprint_sms.api_password' => 'test-password',
        ]);
        Http::fake(['api.sprintsmsservice.com/*' => Http::response(['status' => 'success'])]);
    }

    private function latestSmsCode(): string
    {
        $requests = Http::recorded();
        $message = $requests[count($requests) - 1][0]['textmessage'];
        preg_match('/\b(\d{6})\b/', $message, $matches);

        return $matches[1];
    }
}
