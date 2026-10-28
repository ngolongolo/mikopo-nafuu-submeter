<?php

namespace Tests\Feature;

use App\Support\PhoneNumber;
use Tests\TestCase;

class InterfacePolishTest extends TestCase
{
    public function test_login_has_accessible_password_visibility_control(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('STAFF &amp; PARTNER ACCESS', false)
            ->assertSee('id="login-password"', false)
            ->assertSee('id="password-toggle"', false)
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('/summary-cards.js');
    }

    public function test_summary_cards_use_four_column_layout_and_semantic_icons(): void
    {
        $css = file_get_contents(public_path('summary-cards.css'));
        $javascript = file_get_contents(public_path('summary-cards.js'));

        $this->assertStringContainsString('grid-template-columns:repeat(4', $css);
        $this->assertStringContainsString('summary-card-icon', $css);
        $this->assertStringContainsString("applications:", $javascript);
        $this->assertStringContainsString("payments:", $javascript);
        $this->assertStringContainsString("customers:", $javascript);
    }

    public function test_tanzanian_phone_variants_are_normalized_to_255(): void
    {
        $this->assertSame('255744000100', PhoneNumber::normalize('0744000100'));
        $this->assertSame('255744000100', PhoneNumber::normalize('744 000 100'));
        $this->assertSame('255744000100', PhoneNumber::normalize('+255 744 000 100'));
        $this->assertSame('255744000100', PhoneNumber::normalize('255744000100'));
    }

    public function test_operational_lists_start_with_a_date_column(): void
    {
        foreach ([
            'applications.blade.php',
            'customer-list.blade.php',
            'financiers.blade.php',
            'products.blade.php',
            'supplier-disbursement.blade.php',
            'supplier-disbursements.blade.php',
            'suppliers.blade.php',
            'users.blade.php',
            'partials/loan-table.blade.php',
            'partials/payment-table.blade.php',
        ] as $view) {
            $markup = file_get_contents(resource_path('views/'.$view));
            $this->assertMatchesRegularExpression('/<thead>\s*<tr>\s*<th[^>]*>Date<\/th>/s', $markup, $view);
        }

        $controller = file_get_contents(app_path('Http/Controllers/MarketplaceController.php'));
        $this->assertStringContainsString("latest('paid_at')->latest('id')", $controller);
    }
}
