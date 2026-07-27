<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\SalaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeSalary;
use App\Support\Format;
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
        $deduction = ShopSettings::salaryDefaultDeduction();

        $salaries = EmployeeSalary::query()
            ->with('employee')
            ->whereDate('period_month', $month)
            ->orderByDesc('id')
            ->get();

        $employees = Employee::query()
            ->forAttendance()
            ->orderBy('name')
            ->get();

        return view('admin.salaries.index', [
            'salaries' => $salaries,
            'month' => $month,
            'employees' => $employees,
            'defaultDeduction' => $deduction,
            'format' => Format::class,
            'hasDailyColumns' => Schema::hasColumn('employee_salaries', 'daily_salary'),
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
        $employees = Employee::query()->forAttendance()->orderBy('name')->get();

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
        $base = (float) $employee->base_salary;
        $daily = (float) ($employee->daily_salary ?? 0);
        $workDays = $this->countWorkDays($employee, $period);
        $dailyTotal = $daily * $workDays;
        $deduction = ShopSettings::salaryDefaultDeduction();
        $total = max(0, $base + $dailyTotal + $allowance - $deduction);

        $payload = [
            'base_salary' => $base,
            'allowance' => $allowance,
            'deduction' => $deduction,
            'total' => $total,
            'status' => SalaryStatus::Draft,
            'notes' => $notes,
            'paid_at' => null,
        ];

        if (Schema::hasColumn('employee_salaries', 'daily_salary')) {
            $payload['daily_salary'] = $daily;
            $payload['work_days'] = $workDays;
        }

        return EmployeeSalary::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'period_month' => $period,
            ],
            $payload,
        );
    }

    private function countWorkDays(Employee $employee, Carbon $period): int
    {
        return (int) EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [
                $period->copy()->startOfMonth()->toDateString(),
                $period->copy()->endOfMonth()->toDateString(),
            ])
            ->where('status', AttendanceStatus::Hadir)
            ->whereNotNull('check_in')
            ->count();
    }
}
