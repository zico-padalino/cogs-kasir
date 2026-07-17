<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\KasirPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
        $pin = $this->extractPin($validated, required: true);

        $employee = DB::transaction(function () use ($validated, $pin) {
            unset($validated['pin'], $validated['pin_confirmation']);

            $employee = Employee::query()->create($validated);
            $this->assignKasirPin($employee, $pin);

            return $employee->fresh()->load('user');
        });

        return response()->json([
            'message' => 'Data karyawan berhasil ditambahkan. PIN kasir sudah diset.',
            'data' => $this->formatEmployee($employee),
        ], 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $this->validated($request, $employee);
        $pin = $this->extractPin($validated, required: ! KasirPin::hasPin($employee));

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
        if ($employee->face_photo_path) {
            Storage::disk('public')->delete($employee->face_photo_path);
        }

        $employee->delete();

        return response()->json([
            'message' => 'Data karyawan dihapus.',
        ]);
    }

    public function enrollFace(Request $request, Employee $employee, AttendanceService $attendanceService): JsonResponse
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
            return response()->json([
                'message' => 'Data wajah tidak valid.',
            ], 422);
        }

        try {
            $attendanceService->enrollFace($employee, $validated['photo'], $descriptor);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Wajah karyawan berhasil didaftarkan.',
            'data' => $this->formatEmployee($employee->fresh()->load('user')),
        ]);
    }

    /** @return array<string, mixed> */
    private function formatEmployee(Employee $employee): array
    {
        return [
            ...$employee->toArray(),
            'has_pin' => KasirPin::hasPin($employee),
            'has_face_enrollment' => $employee->hasFaceEnrollment(),
            'face_photo_url' => $employee->face_photo_path
                ? Storage::disk('public')->url($employee->face_photo_path)
                : null,
        ];
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
