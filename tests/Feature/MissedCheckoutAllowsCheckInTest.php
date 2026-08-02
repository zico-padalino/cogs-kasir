<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\AttendanceService;
use App\Support\ShopSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissedCheckoutAllowsCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ShopSettings::put([
            'attendance_enabled' => '1',
            'attendance_latitude' => '-6.2',
            'attendance_longitude' => '106.8',
            'attendance_radius_meters' => '5000',
            'attendance_early_minutes' => '60',
            'attendance_clock_in' => '08:00',
            'attendance_clock_out' => '17:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_check_in_still_allowed_when_previous_day_missing_checkout_and_records_note(): void
    {
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-MISS-001',
            'name' => 'Lupa Kemarin',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
        ]);

        EmployeeAttendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-07-31',
            'check_in' => '16:00:00',
            'status' => AttendanceStatus::Hadir,
            'is_late' => false,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00'));

        $service = app(AttendanceService::class);
        $actions = $service->availableActionsForEmployee($employee);
        $this->assertContains('check_in', $actions);
        $this->assertNotNull($service->missedCheckoutAttendance($employee));

        $today = $service->checkIn($employee, -6.2, 106.8, null);
        $this->assertSame('2026-08-01', $today->work_date->toDateString());
        $this->assertStringContainsString('tidak absen pulang', strtolower((string) $today->notes));

        $yesterday = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '2026-07-31')
            ->first();
        $this->assertNotNull($yesterday);
        $this->assertNull($yesterday->check_out);
        $this->assertStringContainsString('Tidak absen pulang', (string) $yesterday->notes);
    }
}
