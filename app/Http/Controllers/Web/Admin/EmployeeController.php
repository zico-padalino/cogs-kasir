<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeWorkSchedule;
use App\Models\User;
use App\Support\Format;
use App\Support\KasirPin;
use App\Support\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
            'hasDailySalary' => Schema::hasColumn('employees', 'daily_salary'),
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.form', [
            'employee' => new Employee(['employee_code' => Employee::nextCode(), 'status' => EmployeeStatus::Active]),
            'users' => User::query()->orderBy('name')->get(),
            'hasPin' => false,
            'format' => Format::class,
            'schedules' => $this->defaultScheduleRows(),
            'dayLabels' => EmployeeWorkSchedule::dayLabels(),
            'hasSchedules' => Schema::hasTable('employee_work_schedules'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $pin = $this->extractPin($validated);
        $scheduleRows = $this->validatedSchedules($request);

        DB::transaction(function () use ($validated, $pin, $scheduleRows) {
            unset($validated['pin'], $validated['pin_confirmation']);

            $employee = Employee::query()->create($validated);

            if ($pin !== null) {
                $this->assignKasirPin($employee, $pin);
            }

            $this->syncSchedules($employee, $scheduleRows);
        });

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Data karyawan berhasil ditambahkan.');
    }

    public function edit(Employee $employee): View
    {
        $employee->loadMissing(['user', 'workSchedules']);

        return view('admin.employees.form', [
            'employee' => $employee,
            'users' => User::query()->orderBy('name')->get(),
            'hasPin' => KasirPin::hasPin($employee),
            'format' => Format::class,
            'schedules' => $this->scheduleRowsFor($employee),
            'dayLabels' => EmployeeWorkSchedule::dayLabels(),
            'hasSchedules' => Schema::hasTable('employee_work_schedules'),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $this->validated($request, $employee);
        $pin = $this->extractPin($validated);
        $scheduleRows = $this->validatedSchedules($request);

        DB::transaction(function () use ($validated, $employee, $pin, $scheduleRows) {
            unset($validated['pin'], $validated['pin_confirmation']);

            $employee->update($validated);

            if ($pin !== null) {
                $this->assignKasirPin($employee->fresh(), $pin);
            }

            $this->syncSchedules($employee->fresh(), $scheduleRows);
        });

        $message = $pin !== null
            ? 'Data karyawan berhasil diperbarui. PIN kasir sudah disimpan.'
            : 'Data karyawan berhasil diperbarui.';

        return redirect()->route('admin.employees.index')->with('success', $message);
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('admin.employees.index')->with('success', 'Data karyawan dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Employee $employee = null): array
    {
        $validated = $request->validate([
            'employee_code' => ['required', 'string', 'max:32', 'unique:employees,employee_code,'.($employee?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'daily_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'pin' => ['nullable', 'digits_between:4,6'],
            'pin_confirmation' => ['nullable', 'required_with:pin', 'same:pin'],
        ], [
            'pin.digits_between' => 'PIN harus 4–6 digit angka.',
            'pin_confirmation.required_with' => 'Ulangi PIN untuk konfirmasi.',
            'pin_confirmation.same' => 'Konfirmasi PIN tidak cocok.',
        ]);

        $validated['base_salary'] = Format::parseRupiah($validated['base_salary']);

        if (Schema::hasColumn('employees', 'daily_salary')) {
            $validated['daily_salary'] = Format::parseRupiah($validated['daily_salary'] ?? 0);
        } else {
            unset($validated['daily_salary']);
        }

        $validated['user_id'] = $validated['user_id'] ?: null;
        $validated['phone'] = $employee?->phone;
        $validated['position'] = $employee?->position;
        $validated['department'] = $employee?->department;

        return $validated;
    }

    /**
     * @return list<array{day_of_week: int, clock_in: string, clock_out: string, is_off: bool}>
     */
    private function validatedSchedules(Request $request): array
    {
        if (! Schema::hasTable('employee_work_schedules')) {
            return [];
        }

        $request->validate([
            'schedules' => ['nullable', 'array'],
            'schedules.*.enabled' => ['nullable'],
            'schedules.*.clock_in' => ['nullable', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'schedules.*.clock_out' => ['nullable', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
        ], [
            'schedules.*.clock_in.regex' => 'Jam masuk harus format 24 jam HH:mm.',
            'schedules.*.clock_out.regex' => 'Jam pulang harus format 24 jam HH:mm.',
        ]);

        $defaultIn = (string) ShopSettings::get('attendance_clock_in', '08:00');
        $defaultOut = (string) ShopSettings::get('attendance_clock_out', '17:00');
        $input = $request->input('schedules', []);

        $rows = [];
        foreach (range(1, 7) as $day) {
            $row = $input[$day] ?? [];
            $enabled = ! empty($row['enabled']);
            $rows[] = [
                'day_of_week' => $day,
                'clock_in' => $enabled ? (string) ($row['clock_in'] ?? $defaultIn) : $defaultIn,
                'clock_out' => $enabled ? (string) ($row['clock_out'] ?? $defaultOut) : $defaultOut,
                'is_off' => ! $enabled,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{day_of_week: int, clock_in: string, clock_out: string, is_off: bool}>  $rows
     */
    private function syncSchedules(Employee $employee, array $rows): void
    {
        if ($rows === [] || ! Schema::hasTable('employee_work_schedules')) {
            return;
        }

        foreach ($rows as $row) {
            EmployeeWorkSchedule::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'day_of_week' => $row['day_of_week'],
                ],
                [
                    'clock_in' => $row['clock_in'],
                    'clock_out' => $row['clock_out'],
                    'is_off' => $row['is_off'],
                ],
            );
        }
    }

    /**
     * @return array<int, array{enabled: bool, clock_in: string, clock_out: string}>
     */
    private function scheduleRowsFor(Employee $employee): array
    {
        $defaults = $this->defaultScheduleRows();

        if (! Schema::hasTable('employee_work_schedules')) {
            return $defaults;
        }

        $existing = $employee->workSchedules->keyBy('day_of_week');
        if ($existing->isEmpty()) {
            return $defaults;
        }

        $rows = [];
        foreach (range(1, 7) as $day) {
            $row = $existing->get($day);
            $rows[$day] = [
                'enabled' => $row ? ! $row->is_off : false,
                'clock_in' => $row?->clock_in ?? $defaults[$day]['clock_in'],
                'clock_out' => $row?->clock_out ?? $defaults[$day]['clock_out'],
            ];
        }

        return $rows;
    }

    /**
     * Default: Senin–Jumat aktif, jam dari Pengaturan.
     *
     * @return array<int, array{enabled: bool, clock_in: string, clock_out: string}>
     */
    private function defaultScheduleRows(): array
    {
        $clockIn = (string) ShopSettings::get('attendance_clock_in', '08:00');
        $clockOut = (string) ShopSettings::get('attendance_clock_out', '17:00');

        $rows = [];
        foreach (range(1, 7) as $day) {
            $rows[$day] = [
                'enabled' => $day <= 5,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
            ];
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $validated */
    private function extractPin(array $validated): ?string
    {
        $pin = preg_replace('/\D+/', '', (string) ($validated['pin'] ?? '')) ?? '';

        if ($pin === '') {
            return null;
        }

        return $pin;
    }

    private function assignKasirPin(Employee $employee, string $pin): void
    {
        $existing = KasirPin::findByPin($pin);
        if ($existing && $existing->id !== $employee->id) {
            throw ValidationException::withMessages([
                'pin' => 'PIN ini sudah dipakai karyawan lain. Pilih PIN berbeda.',
            ]);
        }

        KasirPin::setPin($employee, $pin);
    }
}
