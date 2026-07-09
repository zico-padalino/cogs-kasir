<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible_at_root(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Selamat datang');
    }

    public function test_authenticated_user_visiting_home_is_redirected(): void
    {
        $user = User::factory()->cogs()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_user_can_login_to_cogs_module(): void
    {
        $user = User::factory()->cogs()->create([
            'email' => 'cogs@test.local',
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
            'module' => UserRole::Cogs->value,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_to_kasir_module(): void
    {
        $user = User::factory()->kasir()->create([
            'email' => 'kasir@test.local',
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
            'module' => UserRole::Kasir->value,
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
            'module' => UserRole::Cogs->value,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_only_shows_cogs_and_kasir_modules(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('COGS')
            ->assertSee('Kasir')
            ->assertDontSee('Admin Karyawan');
    }

    public function test_user_cannot_login_with_wrong_module(): void
    {
        $user = User::factory()->cogs()->create([
            'email' => 'cogs@test.local',
            'password' => 'secret123',
        ]);

        $this->from(route('home'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'secret123',
                'module' => UserRole::Kasir->value,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
