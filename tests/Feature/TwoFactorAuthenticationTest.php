<?php

namespace Tests\Feature;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_requires_email_otp_before_authentication(): void
    {
        $user = User::create(['name' => 'Secure User', 'email' => 'secure@example.test', 'password' => 'strong-password-123', 'role' => 'admin']);
        Mail::fake();

        $this->post('/login', ['email' => $user->email, 'password' => 'strong-password-123'])->assertRedirect('/login/otp');
        $this->assertGuest();
        $this->assertDatabaseHas('login_otps', ['user_id' => $user->id, 'attempts' => 0]);

        LoginOtp::where('user_id', $user->id)->firstOrFail()->update(['code_hash' => Hash::make('123456')]);

        $this->post('/login/otp', ['code' => '123456'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('login_otps', ['user_id' => $user->id]);
    }

    public function test_supplier_lands_on_customer_onboarding_after_otp(): void
    {
        $supplier = User::create(['name' => 'Supplier User', 'email' => 'supplier@example.test', 'password' => 'strong-password-123', 'role' => 'supplier']);
        Mail::fake();

        $this->post('/login', ['email' => $supplier->email, 'password' => 'strong-password-123'])->assertRedirect('/login/otp');
        LoginOtp::where('user_id', $supplier->id)->firstOrFail()->update(['code_hash' => Hash::make('123456')]);

        $this->post('/login/otp', ['code' => '123456'])->assertRedirect('/customers/onboard');
        $this->assertAuthenticatedAs($supplier);
    }
}
