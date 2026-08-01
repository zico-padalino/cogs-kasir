<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCheckoutAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_checkout_employee_who_forgot(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-OUT-001',
            'name' => 'Lupa Pulang',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
        ]);

        $attendance = EmployeeAttendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => today(),
            'check_in' => '16:10:00',
            'status' => AttendanceStatus::Hadir,
            'is_late' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.attendances.checkout', $attendance), [
                'check_out' => '23:59',
            ])
            ->assertRedirect();

        $attendance->refresh();
        $this->assertSame('23:59:00', $attendance->check_out);
        $this->assertStringContainsString('Pulang dicatat admin', (string) $attendance->notes);
    }

    public function test_admin_cannot_checkout_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-OUT-002',
            'name' => 'Sudah Pulang',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
        ]);

        $attendance = EmployeeAttendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => today(),
            'check_in' => '16:10:00',
            'check_out' => '23:50:00',
            'status' => AttendanceStatus::Hadir,
            'is_late' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.attendances.index'))
            ->post(route('admin.attendances.checkout', $attendance), [
                'check_out' => '23:59',
            ])
            ->assertRedirect();

        $attendance->refresh();
        $this->assertSame('23:50:00', $attendance->check_out);
    }
}
