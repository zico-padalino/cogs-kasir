<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke sistem');
    }

    public function test_authenticated_user_visiting_login_is_redirected(): void
    {
        $user = User::factory()->cogs()->create();

        $this->actingAs($user)
            ->get(route('login'))
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

    public function test_user_cannot_login_with_wrong_module(): void
    {
        $user = User::factory()->cogs()->create([
            'email' => 'cogs@test.local',
            'password' => 'secret123',
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'secret123',
                'module' => UserRole::Kasir->value,
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
