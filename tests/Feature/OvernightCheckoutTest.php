<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeWorkSchedule;
use App\Services\AttendanceService;
use App\Support\ShopSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OvernightCheckoutTest extends TestCase
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
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_checkout_after_midnight_attaches_to_yesterdays_shift_when_schedule_ends_2359(): void
    {
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-NIGHT-001',
            'name' => 'Shift Malam',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
        ]);

        if (Schema::hasTable('employee_work_schedules')) {
            // Senin: 16:00–23:59
            EmployeeWorkSchedule::query()->create([
                'employee_id' => $employee->id,
                'day_of_week' => 1,
                'clock_in' => '16:00',
                'clock_out' => '23:59',
                'is_off' => false,
            ]);
        }

        // Senin malam sudah absen masuk
        Carbon::setTestNow(Carbon::parse('2026-07-27 18:00:00')); // Senin
        EmployeeAttendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-07-27',
            'check_in' => '18:00:00',
            'status' => AttendanceStatus::Hadir,
            'is_late' => true,
        ]);

        // Selasa dini hari (lewat 23:59) — harus masih bisa absen pulang ke record Senin
        Carbon::setTestNow(Carbon::parse('2026-07-28 00:20:00'));

        $service = app(AttendanceService::class);
        $this->assertSame('check_out', $service->actionForEmployee($employee));

        $row = $service->checkOut($employee, -6.2, 106.8, null);
        $this->assertSame('2026-07-27', $row->work_date->toDateString());
        $this->assertSame('00:20:00', $row->check_out);
    }

    public function test_overnight_schedule_0100_allows_checkout_after_true_end(): void
    {
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-NIGHT-002',
            'name' => 'Shift Lintas',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
        ]);

        if (! Schema::hasTable('employee_work_schedules')) {
            $this->markTestSkipped('Tabel jadwal belum ada.');
        }

        EmployeeWorkSchedule::query()->create([
            'employee_id' => $employee->id,
            'day_of_week' => 1,
            'clock_in' => '16:00',
            'clock_out' => '01:00',
            'is_off' => false,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-07-27 18:00:00'));
        EmployeeAttendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-07-27',
            'check_in' => '18:00:00',
            'status' => AttendanceStatus::Hadir,
            'is_late' => true,
        ]);

        $service = app(AttendanceService::class);

        // Belum jam 01:00 — belum boleh pulang
        Carbon::setTestNow(Carbon::parse('2026-07-28 00:30:00'));
        $this->assertNotSame('check_out', $service->actionForEmployee($employee));

        Carbon::setTestNow(Carbon::parse('2026-07-28 01:05:00'));
        $this->assertSame('check_out', $service->actionForEmployee($employee));

        $row = $service->checkOut($employee, -6.2, 106.8, null);
        $this->assertSame('2026-07-27', $row->work_date->toDateString());
        $this->assertSame('01:05:00', $row->check_out);
    }
}
