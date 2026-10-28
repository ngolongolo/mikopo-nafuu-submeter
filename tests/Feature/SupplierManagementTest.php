<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_onboarding_rejects_a_phone_already_used_by_another_user(): void
    {
        $this->seed();

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.test',
            'phone' => '0744423287',
            'password' => 'test-password-long',
            'role' => 'admin',
        ]);
        $product = Product::firstOrFail();
        $userCount = User::count();
        $supplierCount = SupplierProfile::count();

        $this->actingAs($admin)
            ->from('/suppliers')
            ->post('/suppliers', [
                'company_name' => 'Duplicate Phone Supplier',
                'contact_name' => 'Nicholaus Ngolongolo',
                'email' => 'nicholaus.ngolongolo@lainafinance.com',
                'phone' => '0744423287',
                'id_type' => 'national_id',
                'id_number' => '19900909132200012232',
                'products' => [$product->id],
            ])
            ->assertRedirect('/suppliers')
            ->assertSessionHasErrors('phone');

        $this->assertSame($userCount, User::count());
        $this->assertSame($supplierCount, SupplierProfile::count());
        $this->assertDatabaseMissing('users', [
            'email' => 'nicholaus.ngolongolo@lainafinance.com',
        ]);
    }

    public function test_creating_an_additional_supplier_user_rejects_an_existing_phone(): void
    {
        $this->seed();

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.test',
            'phone' => '0744423287',
            'password' => 'test-password-long',
            'role' => 'admin',
        ]);
        $owner = User::create([
            'name' => 'Supplier Owner',
            'email' => 'supplier-owner@example.test',
            'phone' => '0755000000',
            'password' => 'test-password-long',
            'role' => 'supplier',
        ]);
        $supplier = SupplierProfile::create([
            'user_id' => $owner->id,
            'company_name' => 'Existing Supplier',
            'phone' => $owner->phone,
            'active' => true,
        ]);
        $userCount = User::count();

        $this->actingAs($admin)
            ->from('/suppliers/'.$supplier->public_id)
            ->post('/suppliers/'.$supplier->public_id.'/users', [
                'name' => 'Additional Supplier User',
                'email' => 'additional-supplier@example.test',
                'phone' => '0744423287',
                'id_type' => 'national_id',
                'id_number' => '19900909132200012233',
            ])
            ->assertRedirect('/suppliers/'.$supplier->public_id)
            ->assertSessionHasErrors('phone');

        $this->assertSame($userCount, User::count());
        $this->assertDatabaseMissing('users', [
            'email' => 'additional-supplier@example.test',
        ]);
    }
}
