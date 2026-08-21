<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SalaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Services\BusinessFundService;
use App\Support\Format;
use App\Support\SalaryCalculator;
use App\Support\ShopSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SalaryApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        if (! Schema::hasTable('employees') || ! Schema::hasTable('employee_salaries')) {
            return response()->json([
                'message' => 'Tabel gaji/karyawan belum lengkap. Jalankan migrasi atau SQL fix_admin_salaries.sql.',
                'data' => [
                    'salaries' => [],
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'month' => $from->format('Y-m'),
                    'default_deduction' => ShopSettings::salaryDefaultDeduction(),
                    'deduction_rates' => ShopSettings::salaryDeductionRates(),
                    'employees' => [],
                    'schema_missing' => true,
                ],
            ], 503);
        }

        $hasPeriodEnd = Schema::hasColumn('employee_salaries', 'period_end');

        $salaries = EmployeeSalary::query()
            ->with('employee')
            ->when(
                $hasPeriodEnd,
                fn ($q) => $q
                    ->whereDate('period_month', '<=', $to)
                    ->whereDate('period_end', '>=', $from),
                fn ($q) => $q->whereDate('period_month', '>=', $from)->whereDate('period_month', '<=', $to),
            )
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'message' => 'Data gaji berhasil dimuat.',
            'data' => [
                'salaries' => $salaries,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'month' => $from->format('Y-m'),
                'default_deduction' => ShopSettings::salaryDefaultDeduction(),
                'deduction_rates' => ShopSettings::salaryDeductionRates(),
                'employees' => Employee::query()->forAttendance()->orderBy('name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'period_from' => ['required_without:period_month', 'nullable', 'date'],
            'period_to' => ['required_without:period_month', 'nullable', 'date', 'after_or_equal:period_from'],
            'period_month' => ['required_without:period_from', 'nullable', 'date_format:Y-m'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::query()->forAttendance()->find($validated['employee_id']);
        if (! $employee) {
            return response()->json([
                'message' => 'Karyawan tidak ditemukan atau tidak boleh digaji.',
            ], 422);
        }

        [$from, $to] = $this->periodFromValidated($validated);
        $allowance = Format::parseRupiah($validated['allowance'] ?? 0);

        $salary = $this->upsertSalary($employee, $from, $to, $allowance, $validated['notes'] ?? null);

        return response()->json([
            'message' => $salary->status === SalaryStatus::Paid
                ? 'Data gaji (sudah bayar) berhasil diperbarui.'
                : 'Gaji berhasil dihitung. Konfirmasi bayar jika sudah dibayarkan.',
            'data' => $salary->load('employee'),
        ], 201);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_from' => ['required_without:period_month', 'nullable', 'date'],
            'period_to' => ['required_without:period_month', 'nullable', 'date', 'after_or_equal:period_from'],
            'period_month' => ['required_without:period_from', 'nullable', 'date_format:Y-m'],
        ]);

        [$from, $to] = $this->periodFromValidated($validated);
        $employees = Employee::query()->forAttendance()->orderBy('name')->get();

        $created = 0;
        foreach ($employees as $employee) {
            $existing = EmployeeSalary::query()
                ->where('employee_id', $employee->id)
                ->whereDate('period_month', $from)
                ->first();

            $this->upsertSalary($employee, $from, $to, (float) ($existing?->allowance ?? 0), $existing?->notes);
            $created++;
        }

        return response()->json([
            'message' => "Gaji otomatis dihitung untuk {$created} karyawan. Konfirmasi bayar jika sudah dibayarkan.",
            'data' => ['count' => $created],
        ]);
    }

    public function markPaid(EmployeeSalary $salary, BusinessFundService $fundService): JsonResponse
    {
        $salary->update([
            'status' => SalaryStatus::Paid,
            'paid_at' => now(),
        ]);

        $fundService->syncSalaryExpense($salary->fresh(['employee']), auth()->user());

        return response()->json([
            'message' => 'Pembayaran dikonfirmasi. Gaji masuk riwayat dan dipotong dari Dana Usaha (omzet bersih).',
            'data' => $salary->fresh()->load('employee'),
        ]);
    }

    public function destroy(EmployeeSalary $salary, BusinessFundService $fundService): JsonResponse
    {
        $fundService->removeSalaryExpense($salary);
        $salary->delete();

        return response()->json([
            'message' => 'Data gaji dihapus. Potongan Dana Usaha ikut dihapus jika ada.',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        if ($request->filled('month') && ! $request->filled('from') && ! $request->filled('to')) {
            $month = Carbon::parse($request->input('month').'-01')->startOfMonth();

            return [$month->copy(), $month->copy()->endOfMonth()->startOfDay()];
        }

        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->startOfDay();

        if ($to->lt($from)) {
            return [$to->copy(), $from->copy()];
        }

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodFromValidated(array $validated): array
    {
        if (! empty($validated['period_from'])) {
            $from = Carbon::parse($validated['period_from'])->startOfDay();
            $to = Carbon::parse($validated['period_to'] ?? $validated['period_from'])->startOfDay();

            return [$from, $to->lt($from) ? $from->copy() : $to];
        }

        $month = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth();

        return [$month->copy(), $month->copy()->endOfMonth()->startOfDay()];
    }

    private function upsertSalary(
        Employee $employee,
        Carbon $from,
        Carbon $to,
        float $allowance = 0,
        ?string $notes = null,
    ): EmployeeSalary {
        $base = (float) $employee->base_salary;
        $daily = (float) ($employee->daily_salary ?? 0);
        $deductionInfo = SalaryCalculator::deductionsFor($employee, $from, $to);
        $workDays = $deductionInfo['work_days'];
        $dailyTotal = $daily * $workDays;
        $deduction = $deductionInfo['total'];
        $total = max(0, $base + $dailyTotal + $allowance - $deduction);

        $existing = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_month', $from)
            ->first();
        $keepPaid = $existing && $existing->status === SalaryStatus::Paid;

        $payload = [
            'base_salary' => $base,
            'allowance' => $allowance,
            'deduction' => $deduction,
            'total' => $total,
            'status' => $keepPaid ? SalaryStatus::Paid : SalaryStatus::Draft,
            'notes' => $this->mergeNotes($notes, $deductionInfo['summary']),
            'paid_at' => $keepPaid ? $existing->paid_at : null,
        ];

        if (Schema::hasColumn('employee_salaries', 'daily_salary')) {
            $payload['daily_salary'] = $daily;
            $payload['work_days'] = $workDays;
        }

        if (Schema::hasColumn('employee_salaries', 'period_end')) {
            $payload['period_end'] = $to->toDateString();
        }

        $salary = EmployeeSalary::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'period_month' => $from->toDateString(),
            ],
            $payload,
        );

        if ($salary->status === SalaryStatus::Paid) {
            app(BusinessFundService::class)->syncSalaryExpense($salary->fresh(['employee']), auth()->user());
        }

        return $salary;
    }

    private function mergeNotes(?string $userNotes, string $autoSummary): ?string
    {
        $user = trim((string) $userNotes);
        $user = preg_replace('/\s*\|\s*Potongan:.*/u', '', $user) ?? $user;
        $user = preg_replace('/^Potongan:.*/u', '', $user) ?? $user;
        $user = trim($user);

        $parts = array_filter([
            $user !== '' ? $user : null,
            $autoSummary !== '' ? 'Potongan: '.$autoSummary : null,
        ]);

        $merged = implode(' | ', $parts);

        return $merged !== '' ? $merged : null;
    }
}
