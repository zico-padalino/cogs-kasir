<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\SalaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalaryController extends Controller
{
    public function index(Request $request): View
    {
        $month = Carbon::parse($request->input('month', now()->format('Y-m')).'-01')->startOfMonth();

        $salaries = EmployeeSalary::query()
            ->with('employee')
            ->whereDate('period_month', $month)
            ->orderByDesc('id')
            ->get();

        return view('admin.salaries.index', [
            'salaries' => $salaries,
            'month' => $month,
            'employees' => Employee::query()->where('status', 'active')->orderBy('name')->get(),
            'format' => Format::class,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'period_month' => ['required', 'date_format:Y-m'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'deduction' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $base = (float) $validated['base_salary'];
        $allowance = (float) ($validated['allowance'] ?? 0);
        $deduction = (float) ($validated['deduction'] ?? 0);
        $period = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth();

        EmployeeSalary::query()->updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'period_month' => $period,
            ],
            [
                'base_salary' => $base,
                'allowance' => $allowance,
                'deduction' => $deduction,
                'total' => $base + $allowance - $deduction,
                'status' => SalaryStatus::Draft,
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return back()->with('success', 'Data gaji berhasil disimpan.');
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
}
