<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\Format;
use App\Support\KasirPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
            'hasPin' => false,
            'format' => Format::class,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $pin = $this->extractPin($validated, required: true);

        DB::transaction(function () use ($validated, $pin) {
            unset($validated['pin'], $validated['pin_confirmation']);

            $employee = Employee::query()->create($validated);
            $this->assignKasirPin($employee, $pin);
        });

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Data karyawan berhasil ditambahkan. PIN kasir sudah diset.');
    }

    public function edit(Employee $employee): View
    {
        $employee->loadMissing('user');

        return view('admin.employees.form', [
            'employee' => $employee,
            'users' => User::query()->orderBy('name')->get(),
            'hasPin' => KasirPin::hasPin($employee),
            'format' => Format::class,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $this->validated($request, $employee);
        $pin = $this->extractPin($validated, required: ! KasirPin::hasPin($employee));

        DB::transaction(function () use ($validated, $employee, $pin) {
            unset($validated['pin'], $validated['pin_confirmation']);

            $employee->update($validated);

            if ($pin !== null) {
                $this->assignKasirPin($employee->fresh(), $pin);
            }
        });

        $message = $pin !== null
            ? 'Data karyawan berhasil diperbarui. PIN kasir sudah disimpan.'
            : 'Data karyawan berhasil diperbarui.';

        return redirect()->route('admin.employees.index')->with('success', $message);
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
        $pinRequired = $employee === null || ! KasirPin::hasPin($employee);

        $validated = $request->validate([
            'employee_code' => ['required', 'string', 'max:32', 'unique:employees,employee_code,'.($employee?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'pin' => [$pinRequired ? 'required' : 'nullable', 'digits_between:4,6'],
            'pin_confirmation' => ['nullable', 'required_with:pin', 'same:pin'],
        ], [
            'pin.required' => 'PIN kasir wajib diisi untuk setiap karyawan.',
            'pin.digits_between' => 'PIN harus 4–6 digit angka.',
            'pin_confirmation.required_with' => 'Ulangi PIN untuk konfirmasi.',
            'pin_confirmation.same' => 'Konfirmasi PIN tidak cocok.',
        ]);

        $validated['user_id'] = $validated['user_id'] ?: null;
        $validated['phone'] = $employee?->phone;
        $validated['position'] = $employee?->position;
        $validated['department'] = $employee?->department;

        return $validated;
    }

    /** @param  array<string, mixed>  $validated */
    private function extractPin(array $validated, bool $required = false): ?string
    {
        $pin = preg_replace('/\D+/', '', (string) ($validated['pin'] ?? '')) ?? '';

        if ($pin === '') {
            if ($required) {
                throw ValidationException::withMessages([
                    'pin' => 'PIN kasir wajib diisi untuk setiap karyawan.',
                ]);
            }

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
