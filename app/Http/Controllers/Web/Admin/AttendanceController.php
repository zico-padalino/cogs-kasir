<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\AttendanceService;
use App\Support\ShopSettings;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendanceService): View
    {
        $from = $request->date('from') ?? ($request->date('date') ?? today());
        $to = $request->date('to') ?? $from;
        if ($to->lt($from)) {
            $to = $from->copy();
        }

        $employeeId = $request->integer('employee_id') ?: null;
        $statusFilter = $request->string('status')->toString();
        $print = $request->boolean('print');

        $query = EmployeeAttendance::query()
            ->with('employee')
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->orderByDesc('work_date')
            ->orderBy('employee_id');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($statusFilter !== '' && in_array($statusFilter, ['hadir', 'izin', 'sakit', 'alpha', 'cuti'], true)) {
            $query->where('status', $statusFilter);
        }

        $attendances = $query->get();
        $settings = $attendanceService->settings();

        $attendances = $attendances->map(function (EmployeeAttendance $row) use ($attendanceService, $settings) {
            $row->check_in_distance = $this->distanceOrNull(
                $attendanceService,
                $settings,
                $row->check_in_lat,
                $row->check_in_lng,
            );
            $row->check_out_distance = $this->distanceOrNull(
                $attendanceService,
                $settings,
                $row->check_out_lat,
                $row->check_out_lng,
            );
            $row->suggested_check_out = $this->suggestedCheckoutTime($row, $attendanceService);

            return $row;
        });

        $pendingCheckout = $attendances
            ->filter(fn (EmployeeAttendance $r) => filled($r->check_in) && ! filled($r->check_out))
            ->values();

        $summary = [
            'total' => $attendances->count(),
            'hadir' => $attendances->where('status', AttendanceStatus::Hadir)->count(),
            'late' => $attendances->where('is_late', true)->count(),
            'no_checkout' => $pendingCheckout->count(),
            'izin' => $attendances->where('status', AttendanceStatus::Izin)->count(),
            'sakit' => $attendances->where('status', AttendanceStatus::Sakit)->count(),
            'alpha' => $attendances->where('status', AttendanceStatus::Alpha)->count(),
            'cuti' => $attendances->where('status', AttendanceStatus::Cuti)->count(),
            'with_selfie' => $attendances->filter(
                fn (EmployeeAttendance $r) => filled($r->check_in_photo_path) || filled($r->check_out_photo_path)
            )->count(),
        ];

        $missingToday = collect();
        if ($from->isSameDay($to) && ! $employeeId) {
            $presentIds = $attendances->pluck('employee_id')->all();
            $requiredIds = $attendanceService->requiredEmployeeIds();

            $missingQuery = Employee::query()
                ->forAttendance()
                ->whereNotIn('id', $presentIds)
                ->orderBy('name');

            if ($requiredIds !== []) {
                $missingQuery->whereIn('id', $requiredIds);
            }

            $missingToday = $missingQuery->get();
        }

        $view = $print ? 'admin.attendances.print' : 'admin.attendances.index';

        return view($view, [
            'attendances' => $attendances,
            'pendingCheckout' => $pendingCheckout,
            'from' => $from,
            'to' => $to,
            'date' => $from,
            'employeeId' => $employeeId,
            'statusFilter' => $statusFilter,
            'summary' => $summary,
            'missingToday' => $missingToday,
            'settings' => $settings,
            'shopName' => ShopSettings::get('shop_name', config('pos.shop_name')),
            'employees' => Employee::query()->forAttendance()->orderBy('name')->get(),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:hadir,izin,sakit,alpha,cuti'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::query()->forAttendance()->find($validated['employee_id']);
        if (! $employee) {
            return back()->withInput()->withErrors([
                'employee_id' => 'Pegawai tidak ditemukan atau tidak boleh dicatat absen.',
            ]);
        }

        $payload = [
            'status' => $validated['status'],
        ];

        if (filled($validated['check_in'] ?? null)) {
            $payload['check_in'] = $this->normalizeTime($validated['check_in']);
        }
        if (filled($validated['check_out'] ?? null)) {
            $payload['check_out'] = $this->normalizeTime($validated['check_out']);
        }
        if (filled($validated['notes'] ?? null)) {
            $payload['notes'] = $validated['notes'];
        }

        $existing = EmployeeAttendance::query()
            ->where('employee_id', $validated['employee_id'])
            ->whereDate('work_date', $validated['work_date'])
            ->first();

        if ($existing) {
            // Jangan hapus jam masuk yang sudah ada jika form jam masuk dikosongkan.
            $existing->update($payload);
        } else {
            EmployeeAttendance::query()->create([
                'employee_id' => $validated['employee_id'],
                'work_date' => $validated['work_date'],
                'is_late' => false,
                ...$payload,
            ]);
        }

        return back()->with('success', 'Absensi berhasil dicatat.');
    }

    /**
     * Admin mencatatkan absen pulang untuk pegawai yang lupa.
     */
    public function checkout(Request $request, EmployeeAttendance $attendance): RedirectResponse
    {
        if (! filled($attendance->check_in)) {
            return back()->withErrors([
                'check_out' => 'Pegawai belum absen masuk; tidak bisa absen pulang.',
            ]);
        }

        if (filled($attendance->check_out)) {
            return back()->with('error', 'Pegawai ini sudah absen pulang.');
        }

        $validated = $request->validate([
            'check_out' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'check_out.required' => 'Jam pulang wajib diisi.',
        ]);

        $adminNote = 'Pulang dicatat admin';
        $extra = trim((string) ($validated['notes'] ?? ''));
        $existingNotes = trim((string) ($attendance->notes ?? ''));

        if ($existingNotes !== '' && str_contains($existingNotes, $adminNote)) {
            $parts = array_filter([$existingNotes, $extra !== '' ? $extra : null]);
        } else {
            $parts = array_filter([
                $existingNotes !== '' ? $existingNotes : null,
                $adminNote,
                $extra !== '' ? $extra : null,
            ]);
        }

        $attendance->update([
            'check_out' => $this->normalizeTime($validated['check_out']),
            'notes' => implode(' · ', $parts),
        ]);

        $name = $attendance->employee?->name ?? 'Pegawai';

        return back()->with('success', "Absen pulang berhasil dicatat untuk {$name}.");
    }

    public function destroy(EmployeeAttendance $attendance): RedirectResponse
    {
        $attendance->delete();

        return back()->with('success', 'Absensi dihapus.');
    }

    /**
     * Stream selfie absensi — menghindari 403 pada /storage di shared hosting.
     */
    public function selfie(EmployeeAttendance $attendance, string $type): Response
    {
        abort_unless(in_array($type, ['in', 'out'], true), 404);

        $path = $type === 'out'
            ? $attendance->check_out_photo_path
            : $attendance->check_in_photo_path;

        abort_unless(filled($path), 404);

        $upload = public_path('uploads/'.$path);
        if (is_file($upload)) {
            return response()->file($upload);
        }

        abort_unless(Storage::disk('public')->exists($path), 404);

        // Mirror ke public/uploads agar request berikutnya tidak bergantung /storage.
        $upload = public_path('uploads/'.$path);
        if (! is_file($upload)) {
            $dir = dirname($upload);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($upload, Storage::disk('public')->get($path));
        }

        return Storage::disk('public')->response($path);
    }

    private function suggestedCheckoutTime(EmployeeAttendance $row, AttendanceService $attendanceService): string
    {
        $workDate = $row->work_date instanceof Carbon
            ? $row->work_date->copy()
            : Carbon::parse((string) $row->work_date);

        $schedule = $attendanceService->scheduleFor($row->employee, $workDate);
        $suggested = $schedule['clock_out'] ?? (string) ShopSettings::get('attendance_clock_out', '23:59');

        return $this->normalizeClockHm($suggested);
    }

    private function normalizeTime(string $time): string
    {
        $hm = $this->normalizeClockHm($time);

        return strlen($hm) === 5 ? $hm.':00' : $hm;
    }

    private function normalizeClockHm(string $time): string
    {
        $value = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) {
            return sprintf('%02d:%02d', min(23, (int) $m[1]), min(59, (int) $m[2]));
        }

        return '23:59';
    }

    private function distanceOrNull(
        AttendanceService $attendanceService,
        array $settings,
        mixed $lat,
        mixed $lng,
    ): ?float {
        if (! $settings['has_location'] || $lat === null || $lng === null) {
            return null;
        }

        return round($attendanceService->distanceMeters(
            $settings['latitude'],
            $settings['longitude'],
            (float) $lat,
            (float) $lng,
        ), 1);
    }
}
