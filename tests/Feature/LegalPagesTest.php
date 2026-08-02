<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'order_day')) {
            Schema::table('pos_orders', function ($table) {
                $table->date('order_day')->nullable()->after('order_number');
            });
        }
    }

    public function test_terms_page_is_public(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Syarat & Ketentuan')
            ->assertSee('Zico Padalino')
            ->assertSee('085161852230')
            ->assertSee('Harga dalam Rupiah')
            ->assertSee(route('order.menu'), false);
    }

    public function test_privacy_page_is_public(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Kebijakan Privasi')
            ->assertSee('Zico Padalino')
            ->assertSee('085161852230')
            ->assertSee(route('legal.terms'), false);
    }

    public function test_order_menu_links_to_legal_pages(): void
    {
        $this->get(route('order.menu'))
            ->assertOk()
            ->assertSee('Syarat & Ketentuan')
            ->assertSee('Kebijakan Privasi')
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee('Harga dalam Rupiah');
    }

    public function test_login_page_links_to_public_menu(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('order.menu'), false);
    }
}
