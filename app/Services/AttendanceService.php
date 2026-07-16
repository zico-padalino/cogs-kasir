<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\User;
use App\Support\ShopSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AttendanceService
{
    public function isEnabled(): bool
    {
        return ShopSettings::get('attendance_enabled', '1') === '1';
    }

    /** @return list<int> */
    public function requiredUserIds(): array
    {
        $raw = trim((string) ShopSettings::get('attendance_required_user_ids', ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', preg_split('/[\s,]+/', $raw) ?: []),
            fn (int $id) => $id > 0,
        )));
    }

    public function mustAttend(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return in_array((int) $user->id, $this->requiredUserIds(), true);
    }

    public function employeeFor(User $user): ?Employee
    {
        return Employee::query()
            ->where('user_id', $user->id)
            ->where('status', EmployeeStatus::Active)
            ->first();
    }

    /**
     * Pastikan akun punya baris Data Karyawan (dibuat dari data user jika belum ada).
     */
    public function ensureEmployeeForUser(User $user): Employee
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();

        if ($employee) {
            if ($employee->status !== EmployeeStatus::Active) {
                $employee->update(['status' => EmployeeStatus::Active]);
            }

            $updates = [];
            if (! filled($employee->name)) {
                $updates['name'] = $user->name;
            }
            if (! filled($employee->email)) {
                $updates['email'] = $user->email;
            }
            if ($updates !== []) {
                $employee->update($updates);
            }

            return $employee->fresh();
        }

        return Employee::query()->create([
            'employee_code' => Employee::nextCode(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => null,
            'position' => null,
            'department' => null,
            'hire_date' => today()->toDateString(),
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
            'user_id' => $user->id,
            'notes' => 'Dibuat otomatis dari akun login',
        ]);
    }

    /**
     * Sinkron semua user yang dicentang wajib absen → Data Karyawan.
     *
     * @param  list<int>  $userIds
     */
    public function syncRequiredEmployees(array $userIds): void
    {
        $users = User::query()->whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            $this->ensureEmployeeForUser($user);
        }
    }

    public function needsProfileSetup(User $user): bool
    {
        if (! $this->mustAttend($user)) {
            return false;
        }

        $employee = $this->ensureEmployeeForUser($user);

        return ! $employee->isProfileComplete();
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'clock_in' => (string) ShopSettings::get('attendance_clock_in', '08:00'),
            'clock_out' => (string) ShopSettings::get('attendance_clock_out', '17:00'),
            'early_minutes' => (int) ShopSettings::get('attendance_early_minutes', '60'),
            'latitude' => (float) ShopSettings::get('attendance_latitude', '0'),
            'longitude' => (float) ShopSettings::get('attendance_longitude', '0'),
            'radius_meters' => (float) ShopSettings::get('attendance_radius_meters', '100'),
            'has_location' => filled(ShopSettings::get('attendance_latitude'))
                && filled(ShopSettings::get('attendance_longitude')),
            'face_threshold' => (float) config('attendance.face_match_threshold', 0.55),
            'required_user_ids' => $this->requiredUserIds(),
        ];
    }

    public function todayAttendance(Employee $employee): ?EmployeeAttendance
    {
        return EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', today())
            ->first();
    }

    /**
     * @return 'check_in'|'check_out'|null
     */
    public function requiredAction(User $user): ?string
    {
        if (! $this->mustAttend($user)) {
            return null;
        }

        if ($this->needsProfileSetup($user)) {
            return null;
        }

        $employee = $this->employeeFor($user);
        if (! $employee) {
            return null;
        }

        $attendance = $this->todayAttendance($employee);

        if ($this->canCheckOutNow($attendance)) {
            return 'check_out';
        }

        if ($this->canCheckInNow($attendance)) {
            return 'check_in';
        }

        return null;
    }

    public function canCheckInNow(?EmployeeAttendance $attendance): bool
    {
        if ($attendance?->check_in) {
            return false;
        }

        $now = now();
        $settings = $this->settings();
        $clockIn = $this->todayAt($settings['clock_in']);
        $clockOut = $this->todayAt($settings['clock_out']);
        $earlyStart = $clockIn->copy()->subMinutes(max(0, $settings['early_minutes']));

        return $now->greaterThanOrEqualTo($earlyStart) && $now->lessThan($clockOut);
    }

    public function canCheckOutNow(?EmployeeAttendance $attendance): bool
    {
        if (! $attendance?->check_in || $attendance->check_out) {
            return false;
        }

        $clockOut = $this->todayAt($this->settings()['clock_out']);

        return now()->greaterThanOrEqualTo($clockOut);
    }

    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function assertWithinRadius(float $lat, float $lng): float
    {
        $settings = $this->settings();
        if (! $settings['has_location']) {
            throw new RuntimeException('Lokasi toko belum diatur di Admin → Pengaturan.');
        }

        $distance = $this->distanceMeters(
            $settings['latitude'],
            $settings['longitude'],
            $lat,
            $lng,
        );

        if ($distance > $settings['radius_meters']) {
            throw new RuntimeException(sprintf(
                'Lokasi di luar area toko (%.0f m dari titik absen, maksimal %.0f m).',
                $distance,
                $settings['radius_meters'],
            ));
        }

        return $distance;
    }

    /**
     * @param  list<float|int>  $a
     * @param  list<float|int>  $b
     */
    public function faceDistance(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        if ($len < 64) {
            throw new RuntimeException('Descriptor wajah tidak valid.');
        }

        $sum = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $diff = ((float) $a[$i]) - ((float) $b[$i]);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * @param  list<float|int>  $descriptor
     */
    public function assertFaceMatch(Employee $employee, array $descriptor): float
    {
        if (! $employee->hasFaceEnrollment()) {
            throw new RuntimeException('Wajah karyawan belum didaftarkan. Hubungi admin.');
        }

        $distance = $this->faceDistance($employee->face_descriptor ?? [], $descriptor);
        $threshold = (float) config('attendance.face_match_threshold', 0.55);

        if ($distance > $threshold) {
            throw new RuntimeException(sprintf(
                'Wajah tidak cocok (skor %.3f > batas %.3f). Coba lagi dengan pencahayaan lebih baik.',
                $distance,
                $threshold,
            ));
        }

        return $distance;
    }

    /**
     * @param  list<float|int>  $descriptor
     */
    public function checkIn(Employee $employee, float $lat, float $lng, string $photoBase64, array $descriptor): EmployeeAttendance
    {
        $attendance = $this->todayAttendance($employee);
        if (! $this->canCheckInNow($attendance)) {
            throw new RuntimeException('Belum waktunya absen masuk, atau Anda sudah absen masuk hari ini.');
        }

        $this->assertWithinRadius($lat, $lng);
        $faceDistance = $this->assertFaceMatch($employee, $descriptor);
        $photoPath = $this->storePhoto($employee, $photoBase64, 'in');

        $settings = $this->settings();
        $clockIn = $this->todayAt($settings['clock_in']);
        $isLate = now()->greaterThan($clockIn);
        $notes = $isLate ? 'Terlambat' : null;

        return EmployeeAttendance::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'work_date' => today()->toDateString(),
            ],
            [
                'check_in' => now()->format('H:i:s'),
                'check_in_lat' => $lat,
                'check_in_lng' => $lng,
                'check_in_photo_path' => $photoPath,
                'check_in_face_distance' => round($faceDistance, 4),
                'status' => AttendanceStatus::Hadir,
                'is_late' => $isLate,
                'notes' => $notes,
            ],
        );
    }

    /**
     * @param  list<float|int>  $descriptor
     */
    public function checkOut(Employee $employee, float $lat, float $lng, string $photoBase64, array $descriptor): EmployeeAttendance
    {
        $attendance = $this->todayAttendance($employee);
        if (! $this->canCheckOutNow($attendance)) {
            throw new RuntimeException('Belum waktunya absen pulang, atau Anda belum absen masuk.');
        }

        $this->assertWithinRadius($lat, $lng);
        $faceDistance = $this->assertFaceMatch($employee, $descriptor);
        $photoPath = $this->storePhoto($employee, $photoBase64, 'out');

        $attendance->update([
            'check_out' => now()->format('H:i:s'),
            'check_out_lat' => $lat,
            'check_out_lng' => $lng,
            'check_out_photo_path' => $photoPath,
            'check_out_face_distance' => round($faceDistance, 4),
        ]);

        return $attendance->fresh();
    }

    /**
     * @param  list<float|int>  $descriptor
     */
    public function enrollFace(Employee $employee, string $photoBase64, array $descriptor): Employee
    {
        if (count($descriptor) < 64) {
            throw new RuntimeException('Descriptor wajah tidak valid. Pastikan wajah terdeteksi jelas.');
        }

        if ($employee->face_photo_path) {
            Storage::disk('public')->delete($employee->face_photo_path);
        }

        $path = $this->storePhoto($employee, $photoBase64, 'enroll');

        $employee->update([
            'face_photo_path' => $path,
            'face_descriptor' => array_values(array_map('floatval', $descriptor)),
        ]);

        return $employee->fresh();
    }

    private function todayAt(string $time): Carbon
    {
        $parts = explode(':', $time);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return today()->setTime($hour, $minute, 0);
    }

    private function storePhoto(Employee $employee, string $photoBase64, string $suffix): string
    {
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $photoBase64, $matches)) {
            throw new RuntimeException('Format foto tidak valid.');
        }

        $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $binary = base64_decode(substr($photoBase64, strpos($photoBase64, ',') + 1), true);
        if ($binary === false || strlen($binary) < 100) {
            throw new RuntimeException('Foto gagal dibaca.');
        }

        if (strlen($binary) > 3_500_000) {
            throw new RuntimeException('Ukuran foto terlalu besar.');
        }

        $path = sprintf(
            'attendance/%d/%s-%s.%s',
            $employee->id,
            today()->format('Ymd'),
            $suffix.'-'.now()->format('His'),
            $ext,
        );

        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
