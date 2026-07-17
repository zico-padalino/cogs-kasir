<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\AttendanceService;
use App\Support\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    public function index(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        $from = $request->date('from') ?? ($request->date('date') ?? today());
        $to = $request->date('to') ?? $from;
        if ($to->lt($from)) {
            $to = $from->copy();
        }

        $employeeId = $request->integer('employee_id') ?: null;
        $statusFilter = $request->string('status')->toString();

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

            return $row;
        });

        $summary = [
            'total' => $attendances->count(),
            'hadir' => $attendances->where('status', AttendanceStatus::Hadir)->count(),
            'late' => $attendances->where('is_late', true)->count(),
            'no_checkout' => $attendances->filter(fn (EmployeeAttendance $r) => filled($r->check_in) && ! filled($r->check_out))->count(),
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
                ->where('status', EmployeeStatus::Active)
                ->whereNotIn('id', $presentIds)
                ->orderBy('name');

            if ($requiredIds !== []) {
                $missingQuery->whereIn('id', $requiredIds);
            }

            $missingToday = $missingQuery->get();
        }

        return response()->json([
            'message' => 'Data absensi berhasil dimuat.',
            'data' => [
                'attendances' => $attendances,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'employee_id' => $employeeId,
                'status_filter' => $statusFilter,
                'summary' => $summary,
                'missing_today' => $missingToday,
                'settings' => $settings,
                'shop_name' => ShopSettings::get('shop_name', config('pos.shop_name')),
                'employees' => Employee::query()->where('status', EmployeeStatus::Active)->orderBy('name')->get(),
                'statuses' => array_map(fn (AttendanceStatus $s) => $s->value, AttendanceStatus::cases()),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:hadir,izin,sakit,alpha,cuti'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $attendance = EmployeeAttendance::query()->updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'work_date' => $validated['work_date'],
            ],
            $validated,
        );

        return response()->json([
            'message' => 'Absensi berhasil dicatat.',
            'data' => $attendance->load('employee'),
        ], 201);
    }

    public function destroy(EmployeeAttendance $attendance): JsonResponse
    {
        $attendance->delete();

        return response()->json([
            'message' => 'Absensi dihapus.',
        ]);
    }

    public function qrInfo(AttendanceService $attendanceService): JsonResponse
    {
        return response()->json([
            'message' => 'Informasi QR absensi.',
            'data' => [
                'shop_name' => ShopSettings::get('shop_name', config('pos.shop_name')),
                'shop_title' => ShopSettings::get('shop_title', config('pos.shop_title')),
                'scan_url' => $attendanceService->publicScanUrl(),
                'settings' => $attendanceService->settings(),
            ],
        ]);
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
