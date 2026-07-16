<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\User;
use App\Support\KasirPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KasirPinTest extends TestCase
{
    use RefreshDatabase;

    private function employeeWithPin(string $name, string $pin, ?User $user = null): Employee
    {
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-'.uniqid(),
            'name' => $name,
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
            'user_id' => $user?->id,
        ]);

        KasirPin::setPin($employee, $pin);

        return $employee->fresh();
    }

    public function test_kasir_routes_require_pin_unlock(): void
    {
        $user = User::factory()->kasir()->create();
        $this->employeeWithPin('Kasir A', '1234', $user);

        $this->actingAs($user)
            ->get(route('kasir.index'))
            ->assertRedirect(route('kasir.pin.unlock'));
    }

    public function test_user_can_unlock_kasir_with_employee_pin(): void
    {
        $user = User::factory()->kasir()->create(['name' => 'Stasiun']);
        $employee = $this->employeeWithPin('Kasir Ani', '2468');

        $this->actingAs($user)
            ->post(route('kasir.pin.unlock.submit'), ['pin' => '2468'])
            ->assertRedirect(route('kasir.index'));

        $this->assertTrue(KasirPin::isUnlocked());
        $this->assertSame($employee->id, KasirPin::operatorEmployee()?->id);
        $this->assertSame('Kasir Ani', KasirPin::operatorName());

        $this->get(route('kasir.index'))->assertOk();
    }

    public function test_employee_without_login_account_can_open_kasir_via_pin(): void
    {
        $station = User::factory()->kasir()->create(['name' => 'Stasiun Kasir']);
        $this->employeeWithPin('Budi', '9999');

        $this->actingAs($station)
            ->post(route('kasir.pin.unlock.submit'), ['pin' => '9999'])
            ->assertRedirect(route('kasir.index'));

        $this->assertSame('Budi', KasirPin::operatorName());
        $this->assertNull(KasirPin::operator());
        $this->assertSame($station->id, KasirPin::operatorOrAuth()?->id);
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $user = User::factory()->kasir()->create();
        $this->employeeWithPin('Kasir', '1111');

        $this->actingAs($user)
            ->from(route('kasir.pin.unlock'))
            ->post(route('kasir.pin.unlock.submit'), ['pin' => '0000'])
            ->assertRedirect(route('kasir.pin.unlock'))
            ->assertSessionHasErrors('pin');
    }

    public function test_kasir_user_can_set_pin_on_linked_employee(): void
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

        $employee = KasirPin::employeeForUser($user->fresh());
        $this->assertNotNull($employee);
        $this->assertTrue(KasirPin::hasPin($employee));
        $this->assertNotNull(KasirPin::findByPin('1357'));
    }

    public function test_pin_setup_page_is_reachable_while_kasir_locked(): void
    {
        $user = User::factory()->kasir()->create();
        $employee = $this->employeeWithPin('Kasir', '1234', $user);
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
        $employee = $this->employeeWithPin('Kasir', '1234', $user);
        KasirPin::unlock($employee);

        $this->actingAs($user)
            ->put(route('pin.update'), [
                'current_password' => 'secret123',
                'pin' => '2468',
                'pin_confirmation' => '2468',
            ])
            ->assertRedirect(route('pin.edit'));
    }
}
