<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeWorkSchedule;
use App\Models\User;
use App\Support\ShopSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
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

    /** @return list<int> */
    public function requiredEmployeeIds(): array
    {
        $raw = trim((string) ShopSettings::get('attendance_required_employee_ids', ''));
        if ($raw !== '') {
            return array_values(array_unique(array_filter(
                array_map('intval', preg_split('/[\s,]+/', $raw) ?: []),
                fn (int $id) => $id > 0,
            )));
        }

        // Kompatibilitas lama: user wajib absen → employee lewat user_id
        $userIds = $this->requiredUserIds();
        if ($userIds === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function mustAttend(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $employeeIds = $this->requiredEmployeeIds();
        if ($employeeIds === []) {
            // Fallback lama berbasis user
            return in_array((int) $user->id, $this->requiredUserIds(), true);
        }

        $employee = $this->employeeFor($user);
        if (! $employee) {
            return in_array((int) $user->id, $this->requiredUserIds(), true);
        }

        return in_array((int) $employee->id, $employeeIds, true);
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
     * @param  list<int>  $userIds
     */
    public function syncRequiredEmployees(array $userIds): void
    {
        $users = User::query()->whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            $this->ensureEmployeeForUser($user);
        }
    }

    /** @return list<Employee> */
    public function requiredEmployees(): array
    {
        $ids = $this->requiredEmployeeIds();
        $query = Employee::query()->forAttendance()->orderBy('name');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->all();
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
        $clockIn = $this->normalizeClock((string) ShopSettings::get('attendance_clock_in', '08:00'));
        $clockOut = $this->normalizeClock((string) ShopSettings::get('attendance_clock_out', '17:00'));

        return [
            'enabled' => $this->isEnabled(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'early_minutes' => (int) ShopSettings::get('attendance_early_minutes', '60'),
            'latitude' => (float) ShopSettings::get('attendance_latitude', '0'),
            'longitude' => (float) ShopSettings::get('attendance_longitude', '0'),
            'radius_meters' => (float) ShopSettings::get('attendance_radius_meters', '100'),
            'has_location' => filled(ShopSettings::get('attendance_latitude'))
                && filled(ShopSettings::get('attendance_longitude')),
            'required_user_ids' => $this->requiredUserIds(),
            'required_employee_ids' => $this->requiredEmployeeIds(),
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

        if ($this->canCheckOutNow($attendance, $employee)) {
            return 'check_out';
        }

        if ($this->canCheckInNow($attendance, $employee)) {
            return 'check_in';
        }

        return null;
    }

    public function canCheckInNow(?EmployeeAttendance $attendance, ?Employee $employee = null): bool
    {
        if ($attendance?->check_in) {
            return false;
        }

        $schedule = $this->scheduleFor($employee);
        if ($schedule['is_off']) {
            return false;
        }

        $now = now();
        $clockIn = $this->todayAt($schedule['clock_in']);
        $clockOut = $this->resolveClockOut($clockIn, $schedule['clock_out']);
        $earlyStart = $clockIn->copy()->subMinutes(max(0, $this->settings()['early_minutes']));

        return $now->greaterThanOrEqualTo($earlyStart) && $now->lessThan($clockOut);
    }

    public function canCheckOutNow(?EmployeeAttendance $attendance, ?Employee $employee = null): bool
    {
        if (! $attendance?->check_in || $attendance->check_out) {
            return false;
        }

        $workDate = $attendance->work_date
            ? Carbon::parse($attendance->work_date->toDateString())
            : today();

        $schedule = $this->scheduleFor($employee, $workDate);
        if ($schedule['is_off']) {
            return false;
        }

        $clockIn = $this->atDate($workDate, $schedule['clock_in']);
        $clockOut = $this->resolveClockOut($clockIn, $schedule['clock_out']);

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

    public function checkIn(Employee $employee, float $lat, float $lng, ?string $photoBase64 = null): EmployeeAttendance
    {
        $attendance = $this->todayAttendance($employee);
        if (! $this->canCheckInNow($attendance, $employee)) {
            throw new RuntimeException('Belum waktunya absen masuk, atau Anda sudah absen masuk hari ini.');
        }

        $this->assertWithinRadius($lat, $lng);
        $photoPath = $photoBase64 ? $this->storePhoto($employee, $photoBase64, 'in') : null;

        $schedule = $this->scheduleFor($employee);
        $clockIn = $this->todayAt($schedule['clock_in']);
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
                'check_in_face_distance' => null,
                'status' => AttendanceStatus::Hadir,
                'is_late' => $isLate,
                'notes' => $notes,
            ],
        );
    }

    public function checkOut(Employee $employee, float $lat, float $lng, ?string $photoBase64 = null): EmployeeAttendance
    {
        $attendance = $this->todayAttendance($employee);
        if (! $this->canCheckOutNow($attendance, $employee)) {
            throw new RuntimeException('Belum waktunya absen pulang, atau Anda belum absen masuk.');
        }

        $this->assertWithinRadius($lat, $lng);
        $photoPath = $photoBase64 ? $this->storePhoto($employee, $photoBase64, 'out') : null;

        $attendance->update([
            'check_out' => now()->format('H:i:s'),
            'check_out_lat' => $lat,
            'check_out_lng' => $lng,
            'check_out_photo_path' => $photoPath,
            'check_out_face_distance' => null,
        ]);

        return $attendance->fresh();
    }

    /**
     * Status absensi hari ini untuk halaman scan publik.
     *
     * @return 'check_in'|'check_out'|'done'|'closed'
     */
    public function actionForEmployee(Employee $employee): string
    {
        $attendance = $this->todayAttendance($employee);

        if ($this->canCheckOutNow($attendance, $employee)) {
            return 'check_out';
        }

        if ($this->canCheckInNow($attendance, $employee)) {
            return 'check_in';
        }

        if ($attendance?->check_in && $attendance?->check_out) {
            return 'done';
        }

        if ($attendance?->check_in) {
            return 'closed';
        }

        return 'closed';
    }

    /** @return list<array{id:int,name:string,code:string,action:string}> */
    public function activeEmployeesForScan(): array
    {
        $requiredIds = $this->requiredEmployeeIds();

        $query = Employee::query()->forAttendance()->orderBy('name');

        if ($requiredIds !== []) {
            $query->whereIn('id', $requiredIds);
        }

        return $query
            ->get()
            ->map(function (Employee $employee) {
                $schedule = $this->scheduleFor($employee);

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'code' => $employee->employee_code,
                    'action' => $this->actionForEmployee($employee),
                    'clock_in' => $schedule['clock_in'],
                    'clock_out' => $schedule['clock_out'],
                    'is_off' => $schedule['is_off'],
                ];
            })
            ->all();
    }

    public function publicScanUrl(): string
    {
        return url('/absensi');
    }

    /**
     * Jadwal kerja pegawai untuk tanggal tertentu.
     * Fallback ke jam global Pengaturan jika belum ada jadwal pribadi.
     *
     * @return array{clock_in: string, clock_out: string, is_off: bool, source: string}
     */
    public function scheduleFor(?Employee $employee, ?Carbon $date = null): array
    {
        $defaults = $this->settings();
        $fallback = [
            'clock_in' => $defaults['clock_in'],
            'clock_out' => $defaults['clock_out'],
            'is_off' => false,
            'source' => 'global',
        ];

        if (! $employee) {
            return $fallback;
        }

        if (! Schema::hasTable('employee_work_schedules')) {
            return $fallback;
        }

        $date ??= now();
        $day = (int) $date->dayOfWeekIso;

        $row = EmployeeWorkSchedule::query()
            ->where('employee_id', $employee->id)
            ->where('day_of_week', $day)
            ->first();

        if (! $row) {
            return $fallback;
        }

        if ($row->is_off) {
            return [
                'clock_in' => $this->normalizeClock((string) $row->clock_in),
                'clock_out' => $this->normalizeClock((string) $row->clock_out),
                'is_off' => true,
                'source' => 'employee',
            ];
        }

        return [
            'clock_in' => $this->normalizeClock((string) $row->clock_in),
            'clock_out' => $this->normalizeClock((string) $row->clock_out),
            'is_off' => false,
            'source' => 'employee',
        ];
    }

    private function todayAt(string $time): Carbon
    {
        return $this->atDate(today(), $time);
    }

    private function atDate(Carbon $date, string $time): Carbon
    {
        $parts = explode(':', $this->normalizeClock($time));
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return $date->copy()->startOfDay()->setTime($hour, $minute, 0);
    }

    /**
     * Jika jam pulang ≤ jam masuk (mis. 16:00 → 00:00), pulang dihitung keesokan harinya.
     */
    private function resolveClockOut(Carbon $clockIn, string $clockOutTime): Carbon
    {
        $clockOut = $this->atDate($clockIn->copy()->startOfDay(), $clockOutTime);

        if ($clockOut->lessThanOrEqualTo($clockIn)) {
            $clockOut->addDay();
        }

        return $clockOut;
    }

    /**
     * Terima input jam 24 jam maupun format lama AM/PM, lalu kembalikan HH:mm.
     */
    private function normalizeClock(string $time): string
    {
        $value = trim($time);
        if ($value === '') {
            return '00:00';
        }

        try {
            return Carbon::createFromFormat('H:i', $value)->format('H:i');
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('g:i A', strtoupper($value))->format('H:i');
            } catch (\Throwable) {
                try {
                    return Carbon::parse($value)->format('H:i');
                } catch (\Throwable) {
                    return '00:00';
                }
            }
        }
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
