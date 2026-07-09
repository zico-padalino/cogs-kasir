<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->date('date') ?? today();

        $attendances = EmployeeAttendance::query()
            ->with('employee')
            ->whereDate('work_date', $date)
            ->orderByDesc('id')
            ->get();

        return view('admin.attendances.index', [
            'attendances' => $attendances,
            'date' => $date,
            'employees' => Employee::query()->where('status', 'active')->orderBy('name')->get(),
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

        EmployeeAttendance::query()->updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'work_date' => $validated['work_date'],
            ],
            $validated,
        );

        return back()->with('success', 'Absensi berhasil dicatat.');
    }

    public function destroy(EmployeeAttendance $attendance): RedirectResponse
    {
        $attendance->delete();

        return back()->with('success', 'Absensi dihapus.');
    }
}
