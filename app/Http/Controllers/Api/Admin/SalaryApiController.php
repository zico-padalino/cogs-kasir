<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SalaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $month = Carbon::parse($request->input('month', now()->format('Y-m')).'-01')->startOfMonth();

        $salaries = EmployeeSalary::query()
            ->with('employee')
            ->whereDate('period_month', $month)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'message' => 'Data gaji berhasil dimuat.',
            'data' => [
                'salaries' => $salaries,
                'month' => $month->format('Y-m'),
                'employees' => Employee::query()->where('status', 'active')->orderBy('name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
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

        $salary = EmployeeSalary::query()->updateOrCreate(
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

        return response()->json([
            'message' => 'Data gaji berhasil disimpan.',
            'data' => $salary->load('employee'),
        ], 201);
    }

    public function markPaid(EmployeeSalary $salary): JsonResponse
    {
        $salary->update([
            'status' => SalaryStatus::Paid,
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Gaji ditandai lunas.',
            'data' => $salary->fresh()->load('employee'),
        ]);
    }

    public function destroy(EmployeeSalary $salary): JsonResponse
    {
        $salary->delete();

        return response()->json([
            'message' => 'Data gaji dihapus.',
        ]);
    }
}
