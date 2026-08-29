<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\SalaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Services\BusinessFundService;
use App\Support\Format;
use App\Support\SalaryCalculator;
use App\Support\ShopSettings;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SalaryController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolvePeriod($request);
        $rates = ShopSettings::salaryDeductionRates();
        $schemaMissing = ! Schema::hasTable('employees') || ! Schema::hasTable('employee_salaries');
        $hasPeriodEnd = Schema::hasTable('employee_salaries')
            && Schema::hasColumn('employee_salaries', 'period_end');
        $hasWaivers = Schema::hasTable('employee_salaries')
            && Schema::hasColumn('employee_salaries', 'deduction_waivers');

        $salaries = $schemaMissing
            ? collect()
            : EmployeeSalary::query()
                ->with(['employee.workSchedules'])
                ->when(
                    $hasPeriodEnd,
                    fn ($q) => $q
                        ->whereDate('period_month', '<=', $to)
                        ->whereDate('period_end', '>=', $from),
                    fn ($q) => $q->whereDate('period_month', '>=', $from)->whereDate('period_month', '<=', $to),
                )
                ->orderByDesc('id')
                ->get();

        $paidSalariesHistory = $schemaMissing
            ? collect()
            : EmployeeSalary::query()
                ->with(['employee.workSchedules'])
                ->where('status', SalaryStatus::Paid)
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->get();

        $employees = $schemaMissing
            ? collect()
            : Employee::query()
                ->forAttendance()
                ->with('workSchedules')
                ->orderBy('name')
                ->get();

        $savedWaiversByEmployee = [];
        $savedManualByEmployee = [];
        foreach ($salaries as $salary) {
            $savedWaiversByEmployee[$salary->employee_id] = $hasWaivers
                ? array_values($salary->deduction_waivers ?? [])
                : [];
            $savedManualByEmployee[$salary->employee_id] = (float) ($salary->manual_deduction ?? 0);
        }

        $hasManualDeduction = Schema::hasTable('employee_salaries')
            && Schema::hasColumn('employee_salaries', 'manual_deduction');

        $previews = [];
        foreach ($employees as $employee) {
            $waivers = $savedWaiversByEmployee[$employee->id] ?? [];
            $manual = $savedManualByEmployee[$employee->id] ?? 0;
            $previews[$employee->id] = $this->previewFor($employee, $from, $to, 0, $waivers, $manual);
        }

        return view('admin.salaries.index', [
            'salaries' => $salaries,
            'paidSalariesHistory' => $paidSalariesHistory,
            'from' => $from,
            'to' => $to,
            'periodLabel' => $this->formatPeriodLabel($from, $to),
            'employees' => $employees,
            'previews' => $previews,
            'defaultDeduction' => $rates['fixed'],
            'deductionRates' => $rates,
            'format' => Format::class,
            'hasDailyColumns' => Schema::hasTable('employee_salaries')
                && Schema::hasColumn('employee_salaries', 'daily_salary'),
            'hasPeriodEnd' => $hasPeriodEnd,
            'hasWaivers' => $hasWaivers,
            'hasManualDeduction' => $hasManualDeduction,
            'schemaMissing' => $schemaMissing,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'manual_deduction' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
            'apply_deduction' => ['nullable', 'array'],
            'apply_deduction.*' => ['string', 'max:64'],
        ]);

        $employee = Employee::query()->forAttendance()->find($validated['employee_id']);
        if (! $employee) {
            return back()->withInput()->withErrors([
                'employee_id' => 'Karyawan tidak ditemukan atau tidak boleh digaji.',
            ]);
        }

        $from = Carbon::parse($validated['period_from'])->startOfDay();
        $to = Carbon::parse($validated['period_to'])->startOfDay();
        $allowance = Format::parseRupiah($validated['allowance'] ?? 0);
        $manualDeduction = Format::parseRupiah($validated['manual_deduction'] ?? 0);

        $existing = $this->findSalaryForPeriod($employee->id, $from);

        // Item yang tidak dicentang = dikecualikan dari potongan.
        $full = SalaryCalculator::deductionsFor($employee, $from, $to);
        $allKeys = array_column($full['items'], 'key');
        $applyKeys = array_map('strval', $validated['apply_deduction'] ?? []);
        // Jika tidak ada item potongan, tidak ada waiver.
        $waivedKeys = $allKeys === []
            ? []
            : array_values(array_diff($allKeys, $applyKeys));

        $this->upsertSalary(
            $employee,
            $from,
            $to,
            $allowance,
            $validated['notes'] ?? null,
            $waivedKeys,
            $manualDeduction,
        );

        $wasPaid = $existing && $existing->status === SalaryStatus::Paid;

        return back()->with(
            'success',
            $wasPaid
                ? 'Data gaji (sudah bayar) berhasil diperbarui.'
                : 'Gaji berhasil dihitung. Konfirmasi bayar jika sudah dibayarkan.'
        );
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:all,one'],
            'employee_id' => ['required_if:scope,one', 'nullable', 'exists:employees,id'],
            'mode' => ['required', 'in:month,week,range'],
            'month' => ['required_if:mode,month', 'nullable', 'date_format:Y-m'],
            'week_date' => ['required_if:mode,week', 'nullable', 'date'],
            'period_from' => ['required_if:mode,range', 'nullable', 'date'],
            'period_to' => ['required_if:mode,range', 'nullable', 'date', 'after_or_equal:period_from'],
        ]);

        [$from, $to] = $this->resolveGeneratePeriod($validated);

        $employeesQuery = Employee::query()->forAttendance()->with('workSchedules')->orderBy('name');
        if ($validated['scope'] === 'one') {
            $employeesQuery->whereKey($validated['employee_id']);
        }
        $employees = $employeesQuery->get();

        if ($validated['scope'] === 'one' && $employees->isEmpty()) {
            return back()->withInput()->withErrors([
                'employee_id' => 'Karyawan tidak ditemukan atau tidak boleh digaji.',
            ]);
        }

        $created = 0;
        foreach ($employees as $employee) {
            $existing = $this->findSalaryForPeriod($employee->id, $from);

            // Generate: pakai waiver + potongan manual tersimpan (jika ada).
            // Yang sudah bayar tetap bisa dihitung ulang (status lunas dipertahankan).
            $waivers = Schema::hasColumn('employee_salaries', 'deduction_waivers')
                ? array_values($existing?->deduction_waivers ?? [])
                : [];
            $manual = Schema::hasColumn('employee_salaries', 'manual_deduction')
                ? (float) ($existing?->manual_deduction ?? 0)
                : 0;

            $this->upsertSalary(
                $employee,
                $from,
                $to,
                (float) ($existing?->allowance ?? 0),
                $existing?->notes,
                $waivers,
                $manual,
            );
            $created++;
        }

        $label = $this->formatPeriodLabel($from, $to);

        if ($validated['scope'] === 'one') {
            $name = $employees->first()?->name ?? 'karyawan';
            $message = "Gaji {$name} periode {$label} berhasil dihitung. Konfirmasi bayar jika sudah dibayarkan.";
        } else {
            $message = "Gaji {$label} dihitung untuk {$created} karyawan. Konfirmasi bayar per karyawan jika sudah dibayarkan.";
        }

        return redirect()
            ->route('admin.salaries.index', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ])
            ->with('success', $message);
    }

    public function markPaid(EmployeeSalary $salary, BusinessFundService $fundService): RedirectResponse
    {
        $salary->update([
            'status' => SalaryStatus::Paid,
            'paid_at' => now(),
        ]);

        $fundService->syncSalaryExpense($salary->fresh(['employee']), auth()->user());

        return back()->with(
            'success',
            'Pembayaran dikonfirmasi. Gaji masuk riwayat dan dipotong dari Dana Usaha (omzet bersih).'
        );
    }

    public function destroy(EmployeeSalary $salary, BusinessFundService $fundService): RedirectResponse
    {
        $fundService->removeSalaryExpense($salary);
        $salary->delete();

        return back()->with('success', 'Data gaji dihapus. Potongan Dana Usaha ikut dihapus jika ada.');
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
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return [$from, $to];
    }

    private function formatPeriodLabel(Carbon $from, Carbon $to): string
    {
        if ($from->toDateString() === $to->toDateString()) {
            return $from->translatedFormat('d M Y');
        }

        if ($from->isSameMonth($to) && $from->isSameYear($to)) {
            return $from->translatedFormat('d').'–'.$to->translatedFormat('d M Y');
        }

        return $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y');
    }

    /**
     * @param  array{mode: string, month?: string|null, week_date?: string|null, period_from?: string|null, period_to?: string|null}  $validated
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveGeneratePeriod(array $validated): array
    {
        return match ($validated['mode']) {
            'month' => (function () use ($validated) {
                $month = Carbon::createFromFormat('Y-m', (string) $validated['month'])->startOfMonth();

                return [$month->copy(), $month->copy()->endOfMonth()->startOfDay()];
            })(),
            'week' => (function () use ($validated) {
                $day = Carbon::parse((string) $validated['week_date'])->startOfDay();
                // Senin–Minggu (ISO).
                $from = $day->copy()->startOfWeek(Carbon::MONDAY);
                $to = $day->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

                return [$from, $to];
            })(),
            default => (function () use ($validated) {
                $from = Carbon::parse((string) $validated['period_from'])->startOfDay();
                $to = Carbon::parse((string) $validated['period_to'])->startOfDay();
                if ($to->lt($from)) {
                    return [$to->copy(), $from->copy()];
                }

                return [$from, $to];
            })(),
        };
    }

    private function findSalaryForPeriod(int $employeeId, Carbon $from): ?EmployeeSalary
    {
        return EmployeeSalary::query()
            ->where('employee_id', $employeeId)
            ->whereDate('period_month', $from)
            ->first();
    }

    /**
     * @param  list<string>  $waivedKeys
     */
    private function upsertSalary(
        Employee $employee,
        Carbon $from,
        Carbon $to,
        float $allowance = 0,
        ?string $notes = null,
        array $waivedKeys = [],
        float $manualDeduction = 0,
    ): EmployeeSalary {
        $preview = $this->previewFor($employee, $from, $to, $allowance, $waivedKeys, $manualDeduction);
        $mergedNotes = $this->mergeNotes($notes, $preview['auto_note']);

        $existing = $this->findSalaryForPeriod($employee->id, $from);
        $keepPaid = $existing && $existing->status === SalaryStatus::Paid;

        $payload = [
            'base_salary' => $preview['base'],
            'allowance' => $preview['allowance'],
            'deduction' => $preview['deduction'],
            'total' => $preview['total'],
            'status' => $keepPaid ? SalaryStatus::Paid : SalaryStatus::Draft,
            'notes' => $mergedNotes,
            'paid_at' => $keepPaid ? $existing->paid_at : null,
        ];

        if (Schema::hasColumn('employee_salaries', 'daily_salary')) {
            $payload['daily_salary'] = $preview['daily'];
            $payload['work_days'] = $preview['work_days'];
        }

        if (Schema::hasColumn('employee_salaries', 'period_end')) {
            $payload['period_end'] = $to->toDateString();
        }

        if (Schema::hasColumn('employee_salaries', 'deduction_waivers')) {
            $payload['deduction_waivers'] = array_values($waivedKeys);
        }

        if (Schema::hasColumn('employee_salaries', 'manual_deduction')) {
            $payload['manual_deduction'] = $preview['manual_deduction'];
        }

        $salary = EmployeeSalary::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'period_month' => $from->toDateString(),
            ],
            $payload,
        );

        $this->syncPaidExpenseIfNeeded($salary);

        return $salary;
    }

    private function syncPaidExpenseIfNeeded(EmployeeSalary $salary): void
    {
        if ($salary->status !== SalaryStatus::Paid) {
            return;
        }

        app(BusinessFundService::class)->syncSalaryExpense($salary->fresh(['employee']), auth()->user());
    }

    /**
     * @param  list<string>  $waivedKeys
     * @return array{
     *     base: float,
     *     daily: float,
     *     days_week: int,
     *     weekly: float,
     *     work_days: int,
     *     daily_total: float,
     *     allowance: float,
     *     auto_deduction: float,
     *     manual_deduction: float,
     *     deduction: float,
     *     deduction_summary: string,
     *     deduction_items: list<array<string, mixed>>,
     *     waived_keys: list<string>,
     *     attendance_days: list<array<string, mixed>>,
     *     employee_name: string,
     *     total: float,
     *     auto_note: string
     * }
     */
    private function previewFor(
        Employee $employee,
        Carbon $from,
        Carbon $to,
        float $allowance = 0,
        array $waivedKeys = [],
        float $manualDeduction = 0,
    ): array {
        $base = (float) $employee->base_salary;
        $daily = (float) ($employee->daily_salary ?? 0);
        $deductionInfo = SalaryCalculator::deductionsFor($employee, $from, $to, $waivedKeys);
        $attendanceDays = SalaryCalculator::attendanceDaysFor($employee, $from, $to);
        $workDays = (int) $deductionInfo['work_days'];
        $dailyTotal = $daily * $workDays;
        $daysPerWeek = $employee->scheduledWorkDaysPerWeek();
        $weekly = $daily * $daysPerWeek;
        $autoDeduction = (float) $deductionInfo['total'];
        $manual = max(0, $manualDeduction);
        $deduction = $autoDeduction + $manual;
        $total = max(0, $base + $dailyTotal + $allowance - $deduction);

        $payParts = [];
        if ($daily > 0 || $workDays > 0) {
            $payParts[] = 'Harian '.Format::rupiah($daily).' × '.$workDays.' hari hadir = '.Format::rupiah($dailyTotal);
            $payParts[] = '≈ / minggu '.Format::rupiah($weekly).' ('.$daysPerWeek.' hari jadwal)';
        }
        if ($deductionInfo['summary'] !== '') {
            $payParts[] = 'Potongan: '.$deductionInfo['summary'];
        }
        if ($manual > 0) {
            $payParts[] = 'Potongan manual '.Format::rupiah($manual);
        }

        return [
            'base' => $base,
            'daily' => $daily,
            'days_week' => $daysPerWeek,
            'weekly' => $weekly,
            'work_days' => $workDays,
            'daily_total' => $dailyTotal,
            'allowance' => $allowance,
            'auto_deduction' => $autoDeduction,
            'manual_deduction' => $manual,
            'deduction' => $deduction,
            'deduction_summary' => $deductionInfo['summary'],
            'deduction_items' => $deductionInfo['items'],
            'waived_keys' => $deductionInfo['waived_keys'],
            'attendance_days' => $attendanceDays,
            'employee_name' => $employee->name,
            'total' => $total,
            'auto_note' => implode(' · ', $payParts),
        ];
    }

    private function mergeNotes(?string $userNotes, string $autoSummary): ?string
    {
        $user = trim((string) $userNotes);
        $user = preg_replace('/\s*\|\s*(Harian|Potongan|≈).*/u', '', $user) ?? $user;
        $user = preg_replace('/^(Harian|Potongan|≈).*/u', '', $user) ?? $user;
        $user = trim($user);

        $parts = array_filter([
            $user !== '' ? $user : null,
            $autoSummary !== '' ? $autoSummary : null,
        ]);

        $merged = implode(' | ', $parts);

        return $merged !== '' ? $merged : null;
    }
}
