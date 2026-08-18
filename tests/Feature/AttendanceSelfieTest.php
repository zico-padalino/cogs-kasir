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
            ->assertSee('Ambil selfie sebagai bukti absen')
            ->assertSee('Izinkan lokasi')
            ->assertSee('data-scan-gps-enable', false);
    }

    public function test_logged_in_user_still_sees_public_attendance_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('attendance.scan'))
            ->assertOk()
            ->assertSee('Absensi QR')
            ->assertSee('Ambil selfie sebagai bukti absen')
            ->assertSee('Izinkan lokasi');
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

    public function test_admin_can_view_attendance_selfie_via_app_route(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-SELFIE-003',
            'name' => 'Pegawai Foto',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
        ]);

        $path = 'attendance/'.$employee->id.'/test-in.jpg';
        $bytes = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z');
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $bytes);

        $attendance = \App\Models\EmployeeAttendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => today(),
            'check_in' => '09:00:00',
            'check_in_photo_path' => $path,
            'status' => \App\Enums\AttendanceStatus::Hadir,
            'is_late' => false,
        ]);

        $url = $attendance->checkInPhotoUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('/selfie/in', $url);

        $this->actingAs($admin)
            ->get(route('admin.attendances.selfie', ['attendance' => $attendance, 'type' => 'in']))
            ->assertOk();
    }
}
