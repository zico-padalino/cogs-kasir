<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\SalaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
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
        $month = Carbon::parse($request->input('month', now()->format('Y-m')).'-01')->startOfMonth();
        $rates = ShopSettings::salaryDeductionRates();
        $schemaMissing = ! Schema::hasTable('employees') || ! Schema::hasTable('employee_salaries');

        $salaries = $schemaMissing
            ? collect()
            : EmployeeSalary::query()
                ->with(['employee.workSchedules'])
                ->whereDate('period_month', $month)
                ->orderByDesc('id')
                ->get();

        $employees = $schemaMissing
            ? collect()
            : Employee::query()
                ->forAttendance()
                ->with('workSchedules')
                ->orderBy('name')
                ->get();

        $previews = [];
        foreach ($employees as $employee) {
            $previews[$employee->id] = $this->previewFor($employee, $month);
        }

        return view('admin.salaries.index', [
            'salaries' => $salaries,
            'month' => $month,
            'employees' => $employees,
            'previews' => $previews,
            'defaultDeduction' => $rates['fixed'],
            'deductionRates' => $rates,
            'format' => Format::class,
            'hasDailyColumns' => Schema::hasTable('employee_salaries')
                && Schema::hasColumn('employee_salaries', 'daily_salary'),
            'schemaMissing' => $schemaMissing,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'period_month' => ['required', 'date_format:Y-m'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::query()->forAttendance()->find($validated['employee_id']);
        if (! $employee) {
            return back()->withInput()->withErrors([
                'employee_id' => 'Karyawan tidak ditemukan atau tidak boleh digaji.',
            ]);
        }

        $period = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth();
        $allowance = Format::parseRupiah($validated['allowance'] ?? 0);

        $existing = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_month', $period)
            ->first();

        if ($existing && $existing->status === SalaryStatus::Paid) {
            return back()->withInput()->withErrors([
                'employee_id' => 'Gaji karyawan ini sudah lunas untuk periode tersebut.',
            ]);
        }

        $this->upsertSalary(
            $employee,
            $period,
            $allowance,
            $validated['notes'] ?? null,
        );

        return back()->with('success', 'Data gaji berhasil dihitung dan disimpan.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);

        $period = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth();
        $employees = Employee::query()->forAttendance()->with('workSchedules')->orderBy('name')->get();

        $created = 0;
        foreach ($employees as $employee) {
            $existing = EmployeeSalary::query()
                ->where('employee_id', $employee->id)
                ->whereDate('period_month', $period)
                ->first();

            if ($existing && $existing->status === SalaryStatus::Paid) {
                continue;
            }

            $this->upsertSalary($employee, $period, (float) ($existing?->allowance ?? 0), $existing?->notes);
            $created++;
        }

        return back()->with('success', "Gaji otomatis dihitung untuk {$created} karyawan.");
    }

    public function markPaid(EmployeeSalary $salary): RedirectResponse
    {
        $salary->update([
            'status' => SalaryStatus::Paid,
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Gaji ditandai lunas.');
    }

    public function destroy(EmployeeSalary $salary): RedirectResponse
    {
        $salary->delete();

        return back()->with('success', 'Data gaji dihapus.');
    }

    private function upsertSalary(
        Employee $employee,
        Carbon $period,
        float $allowance = 0,
        ?string $notes = null,
    ): EmployeeSalary {
        $preview = $this->previewFor($employee, $period, $allowance);
        $mergedNotes = $this->mergeNotes($notes, $preview['auto_note']);

        $payload = [
            'base_salary' => $preview['base'],
            'allowance' => $preview['allowance'],
            'deduction' => $preview['deduction'],
            'total' => $preview['total'],
            'status' => SalaryStatus::Draft,
            'notes' => $mergedNotes,
            'paid_at' => null,
        ];

        if (Schema::hasColumn('employee_salaries', 'daily_salary')) {
            $payload['daily_salary'] = $preview['daily'];
            $payload['work_days'] = $preview['work_days'];
        }

        return EmployeeSalary::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'period_month' => $period,
            ],
            $payload,
        );
    }

    /**
     * Ringkasan hitungan gaji (sama dipakai preview UI & saat simpan).
     *
     * @return array{
     *     base: float,
     *     daily: float,
     *     days_week: int,
     *     weekly: float,
     *     work_days: int,
     *     daily_total: float,
     *     allowance: float,
     *     deduction: float,
     *     deduction_summary: string,
     *     total: float,
     *     auto_note: string
     * }
     */
    private function previewFor(Employee $employee, Carbon $period, float $allowance = 0): array
    {
        $base = (float) $employee->base_salary;
        $daily = (float) ($employee->daily_salary ?? 0);
        $deductionInfo = SalaryCalculator::deductionsFor($employee, $period);
        $workDays = (int) $deductionInfo['work_days'];
        $dailyTotal = $daily * $workDays;
        $daysPerWeek = $employee->scheduledWorkDaysPerWeek();
        $weekly = $daily * $daysPerWeek;
        $deduction = (float) $deductionInfo['total'];
        $total = max(0, $base + $dailyTotal + $allowance - $deduction);

        $payParts = [];
        if ($daily > 0 || $workDays > 0) {
            $payParts[] = 'Harian '.Format::rupiah($daily).' × '.$workDays.' hari hadir = '.Format::rupiah($dailyTotal);
            $payParts[] = '≈ / minggu '.Format::rupiah($weekly).' ('.$daysPerWeek.' hari jadwal)';
        }
        if ($deductionInfo['summary'] !== '') {
            $payParts[] = 'Potongan: '.$deductionInfo['summary'];
        }

        return [
            'base' => $base,
            'daily' => $daily,
            'days_week' => $daysPerWeek,
            'weekly' => $weekly,
            'work_days' => $workDays,
            'daily_total' => $dailyTotal,
            'allowance' => $allowance,
            'deduction' => $deduction,
            'deduction_summary' => $deductionInfo['summary'],
            'total' => $total,
            'auto_note' => implode(' · ', $payParts),
        ];
    }

    private function mergeNotes(?string $userNotes, string $autoSummary): ?string
    {
        $user = trim((string) $userNotes);
        // Hapus ringkasan otomatis sebelumnya agar tidak menumpuk.
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
