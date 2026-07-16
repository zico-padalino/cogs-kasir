<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class EmployeeController extends Controller
{
    public function index(): View
    {
        $employees = Employee::query()
            ->with('user')
            ->orderByDesc('id')
            ->get();

        return view('admin.employees.index', [
            'employees' => $employees,
            'format' => Format::class,
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.form', [
            'employee' => new Employee(['employee_code' => Employee::nextCode(), 'status' => EmployeeStatus::Active]),
            'users' => User::query()->orderBy('name')->get(),
            'format' => Format::class,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        Employee::query()->create($validated);

        return redirect()->route('admin.employees.index')->with('success', 'Data karyawan berhasil ditambahkan.');
    }

    public function edit(Employee $employee): View
    {
        return view('admin.employees.form', [
            'employee' => $employee,
            'users' => User::query()->orderBy('name')->get(),
            'format' => Format::class,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update($this->validated($request, $employee));

        return redirect()->route('admin.employees.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->face_photo_path) {
            Storage::disk('public')->delete($employee->face_photo_path);
        }

        $employee->delete();

        return redirect()->route('admin.employees.index')->with('success', 'Data karyawan dihapus.');
    }

    public function enrollFace(Request $request, Employee $employee, AttendanceService $attendanceService): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'string'],
            'descriptor' => ['required', 'string'],
        ], [
            'photo.required' => 'Foto wajah wajib diambil.',
            'descriptor.required' => 'Wajah belum terdeteksi.',
        ]);

        $descriptor = json_decode($validated['descriptor'], true);
        if (! is_array($descriptor)) {
            return back()->with('error', 'Data wajah tidak valid.');
        }

        try {
            $attendanceService->enrollFace($employee, $validated['photo'], $descriptor);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Wajah karyawan berhasil didaftarkan.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Employee $employee = null): array
    {
        $validated = $request->validate([
            'employee_code' => ['required', 'string', 'max:32', 'unique:employees,employee_code,'.($employee?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['user_id'] = $validated['user_id'] ?: null;

        return $validated;
    }
}
