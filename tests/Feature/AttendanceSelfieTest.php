<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSelfieTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_public_attendance_without_login(): void
    {
        $this->assertGuest();

        $this->get(route('attendance.scan'))
            ->assertOk()
            ->assertSee('Absensi QR')
            ->assertSee('Ambil selfie sebagai bukti absen');
    }

    public function test_logged_in_user_still_sees_public_attendance_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('attendance.scan'))
            ->assertOk()
            ->assertSee('Absensi QR')
            ->assertSee('Ambil selfie sebagai bukti absen');
    }

    public function test_employee_without_registered_face_can_use_public_selfie_attendance(): void
    {
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-SELFIE-001',
            'name' => 'Pegawai Selfie',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
        ]);

        $this->get(route('attendance.scan'))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee('Tidak perlu daftar wajah')
            ->assertDontSee('daftarkan wajah', false);
    }

    public function test_admin_employee_pages_explain_selfie_without_face_enrollment_form(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-SELFIE-002',
            'name' => 'Pegawai Tanpa Biometrik',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Absen cukup selfie + GPS')
            ->assertDontSee('Wajah belum didaftar');

        $this->actingAs($admin)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->assertSee('Tidak perlu mendaftarkan wajah')
            ->assertDontSee('Daftarkan wajah')
            ->assertDontSee('Simpan wajah dari kamera');
    }
}
