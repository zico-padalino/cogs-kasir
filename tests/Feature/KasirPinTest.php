<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\KasirPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KasirPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_routes_require_pin_unlock(): void
    {
        $user = User::factory()->kasir()->create();
        KasirPin::setPin($user, '1234');

        $this->actingAs($user)
            ->get(route('kasir.index'))
            ->assertRedirect(route('kasir.pin.unlock'));
    }

    public function test_user_can_unlock_kasir_with_own_pin(): void
    {
        $user = User::factory()->kasir()->create([
            'name' => 'Kasir Ani',
        ]);
        KasirPin::setPin($user, '2468');

        $this->actingAs($user)
            ->post(route('kasir.pin.unlock.submit'), ['pin' => '2468'])
            ->assertRedirect(route('kasir.index'));

        $this->assertTrue(KasirPin::isUnlocked());
        $this->assertSame($user->id, KasirPin::operator()?->id);

        $this->get(route('kasir.index'))->assertOk();
    }

    public function test_shared_station_can_open_as_another_kasir_via_pin(): void
    {
        $station = User::factory()->kasir()->create(['name' => 'Stasiun Kasir']);
        $operator = User::factory()->kasir()->create(['name' => 'Budi']);
        KasirPin::setPin($operator, '9999');

        $this->actingAs($station)
            ->post(route('kasir.pin.unlock.submit'), ['pin' => '9999'])
            ->assertRedirect(route('kasir.index'));

        $this->assertSame('Budi', KasirPin::operator()?->name);
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $user = User::factory()->kasir()->create();
        KasirPin::setPin($user, '1111');

        $this->actingAs($user)
            ->from(route('kasir.pin.unlock'))
            ->post(route('kasir.pin.unlock.submit'), ['pin' => '0000'])
            ->assertRedirect(route('kasir.pin.unlock'))
            ->assertSessionHasErrors('pin');
    }

    public function test_kasir_user_can_set_pin(): void
    {
        $user = User::factory()->kasir()->create([
            'password' => Hash::make('secret123'),
        ]);

        $this->actingAs($user)
            ->put(route('pin.update'), [
                'current_password' => 'secret123',
                'pin' => '1357',
                'pin_confirmation' => '1357',
            ])
            ->assertRedirect(route('kasir.pin.unlock'));

        $user->refresh();
        $this->assertTrue(KasirPin::hasPin($user));
        $this->assertNotNull(KasirPin::findByPin('1357'));
    }

    public function test_pin_setup_page_is_reachable_while_kasir_locked(): void
    {
        $user = User::factory()->kasir()->create();
        KasirPin::setPin($user, '1234');
        KasirPin::lock();

        $this->actingAs($user)
            ->get(route('pin.edit'))
            ->assertOk()
            ->assertSee('PIN baru', false);
    }

    public function test_unlocked_kasir_stays_on_pin_edit_after_update(): void
    {
        $user = User::factory()->kasir()->create([
            'password' => Hash::make('secret123'),
        ]);
        KasirPin::setPin($user, '1234');
        KasirPin::unlock($user);

        $this->actingAs($user)
            ->put(route('pin.update'), [
                'current_password' => 'secret123',
                'pin' => '2468',
                'pin_confirmation' => '2468',
            ])
            ->assertRedirect(route('pin.edit'));
    }
}
