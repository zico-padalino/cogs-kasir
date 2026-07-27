<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Support\Format;
use App\Support\KasirPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EmployeeApiController extends Controller
{
    public function index(): JsonResponse
    {
        $employees = Employee::query()
            ->with('user')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Employee $employee) => $this->formatEmployee($employee));

        return response()->json([
            'message' => 'Daftar karyawan berhasil dimuat.',
            'data' => $employees,
        ]);
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->loadMissing('user');

        return response()->json([
            'message' => 'Detail karyawan berhasil dimuat.',
            'data' => $this->formatEmployee($employee),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $pin = $this->extractPin($validated);

        $employee = DB::transaction(function () use ($validated, $pin) {
            unset($validated['pin'], $validated['pin_confirmation']);

            $employee = Employee::query()->create($validated);

            if ($pin !== null) {
                $this->assignKasirPin($employee, $pin);
            }

            return $employee->fresh()->load('user');
        });

        return response()->json([
            'message' => 'Data karyawan berhasil ditambahkan.',
            'data' => $this->formatEmployee($employee),
        ], 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $this->validated($request, $employee);
        $pin = $this->extractPin($validated);

        $employee = DB::transaction(function () use ($validated, $employee, $pin) {
            unset($validated['pin'], $validated['pin_confirmation']);

            $employee->update($validated);

            if ($pin !== null) {
                $this->assignKasirPin($employee->fresh(), $pin);
            }

            return $employee->fresh()->load('user');
        });

        $message = $pin !== null
            ? 'Data karyawan berhasil diperbarui. PIN kasir sudah disimpan.'
            : 'Data karyawan berhasil diperbarui.';

        return response()->json([
            'message' => $message,
            'data' => $this->formatEmployee($employee),
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json([
            'message' => 'Data karyawan dihapus.',
        ]);
    }

    /** @return array<string, mixed> */
    private function formatEmployee(Employee $employee): array
    {
        return [
            ...$employee->toArray(),
            'has_pin' => KasirPin::hasPin($employee),
            'attendance_method' => 'selfie_gps',
        ];
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
