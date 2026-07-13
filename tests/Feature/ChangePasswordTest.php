<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_password_page(): void
    {
        $user = User::factory()->kasir()->create();

        $this->actingAs($user)
            ->get(route('password.edit'))
            ->assertOk()
            ->assertSee('Ubah Password')
            ->assertSee($user->email);
    }

    public function test_guest_cannot_open_password_page(): void
    {
        $this->get(route('password.edit'))
            ->assertRedirect(route('home'));
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->admin()->create([
            'password' => Hash::make('password-lama'),
        ]);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'password-lama',
                'password' => 'password-baru1',
                'password_confirmation' => 'password-baru1',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('password-baru1', $user->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->cogs()->create([
            'password' => Hash::make('password-benar'),
        ]);

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'salah',
                'password' => 'password-baru1',
                'password_confirmation' => 'password-baru1',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHasErrors('current_password');
    }
}
