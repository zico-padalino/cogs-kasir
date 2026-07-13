<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible_at_root(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Masuk')
            ->assertSee('Gunakan email dan password akun Anda')
            ->assertDontSee('Pilih modul');
    }

    public function test_guest_cannot_open_kasir_or_admin_without_login(): void
    {
        $this->get(route('kasir.index'))
            ->assertRedirect(route('home'));

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('home'));

        $this->get('/dashboard')
            ->assertRedirect(route('home'));
    }

    public function test_guest_opening_kasir_is_sent_back_after_login(): void
    {
        $user = User::factory()->kasir()->create([
            'email' => 'kasir-intended@test.local',
            'password' => 'secret123',
        ]);

        $this->get(route('kasir.index'))->assertRedirect(route('home'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('kasir.index'));
    }

    public function test_authenticated_user_visiting_home_is_redirected(): void
    {
        $user = User::factory()->cogs()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('overhead-rates.index'));
    }

    public function test_cogs_user_is_redirected_to_hitung_biaya(): void
    {
        $user = User::factory()->cogs()->create([
            'email' => 'cogs@test.local',
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('overhead-rates.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_kasir_user_is_redirected_to_kasir(): void
    {
        $user = User::factory()->kasir()->create([
            'email' => 'kasir@test.local',
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('kasir.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_user_is_redirected_to_admin_panel(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@test.local',
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_does_not_ask_for_module_choice(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Masuk')
            ->assertDontSee('Pilih modul')
            ->assertDontSee('auth-module-tab', false);
    }
}
