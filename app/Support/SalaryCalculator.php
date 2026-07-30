<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeWorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class SalaryCalculator
{
    /**
     * Hitung potongan dari setting + absensi bulan tersebut.
     *
     * @return array{
     *     total: float,
     *     fixed: float,
     *     late_count: int,
     *     late_amount: float,
     *     late_after_minutes: int,
     *     alpha_count: int,
     *     alpha_amount: float,
     *     izin_count: int,
     *     izin_amount: float,
     *     sakit_count: int,
     *     sakit_amount: float,
     *     work_days: int,
     *     summary: string
     * }
     */
    public static function deductionsFor(Employee $employee, Carbon $period): array
    {
        $rates = ShopSettings::salaryDeductionRates();
        $from = $period->copy()->startOfMonth()->toDateString();
        $to = $period->copy()->endOfMonth()->toDateString();
        $defaultClockIn = (string) ShopSettings::get('attendance_clock_in', '08:00');
        $graceMinutes = $rates['late_after_minutes'];

        $scheduleByDay = [];
        if (Schema::hasTable('employee_work_schedules')) {
            $scheduleByDay = EmployeeWorkSchedule::query()
                ->where('employee_id', $employee->id)
                ->get()
                ->keyBy('day_of_week');
        }

        $rows = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->get();

        $workDays = $rows
            ->filter(fn (EmployeeAttendance $row) => $row->status === AttendanceStatus::Hadir && filled($row->check_in))
            ->count();

        $lateCount = $rows
            ->filter(function (EmployeeAttendance $row) use ($scheduleByDay, $defaultClockIn, $graceMinutes) {
                $workDate = $row->work_date instanceof Carbon
                    ? $row->work_date
                    : Carbon::parse((string) $row->work_date);
                $day = (int) $workDate->dayOfWeekIso;
                $schedule = $scheduleByDay[$day] ?? null;
                $clockIn = $schedule && ! $schedule->is_off
                    ? (string) $schedule->clock_in
                    : $defaultClockIn;

                return self::isLateForDeduction($row, $clockIn, $graceMinutes);
            })
            ->count();

        $alphaCount = $rows
            ->filter(fn (EmployeeAttendance $row) => $row->status === AttendanceStatus::Alpha)
            ->count();

        $izinCount = $rows
            ->filter(fn (EmployeeAttendance $row) => $row->status === AttendanceStatus::Izin)
            ->count();

        $sakitCount = $rows
            ->filter(fn (EmployeeAttendance $row) => $row->status === AttendanceStatus::Sakit)
            ->count();

        $fixed = $rates['fixed'];
        $lateAmount = $lateCount * $rates['late'];
        $alphaAmount = $alphaCount * $rates['alpha'];
        $izinAmount = $izinCount * $rates['izin'];
        $sakitAmount = $sakitCount * $rates['sakit'];
        $total = $fixed + $lateAmount + $alphaAmount + $izinAmount + $sakitAmount;

        $parts = [];
        if ($fixed > 0) {
            $parts[] = 'Rutin '.Format::rupiah($fixed);
        }
        if ($lateAmount > 0) {
            $graceLabel = $graceMinutes > 0 ? " (≥{$graceMinutes} mnt)" : '';
            $parts[] = "Telat {$lateCount}× ".Format::rupiah($rates['late']).$graceLabel;
        }
        if ($alphaAmount > 0) {
            $parts[] = "Alpha {$alphaCount}× ".Format::rupiah($rates['alpha']);
        }
        if ($izinAmount > 0) {
            $parts[] = "Izin {$izinCount}× ".Format::rupiah($rates['izin']);
        }
        if ($sakitAmount > 0) {
            $parts[] = "Sakit {$sakitCount}× ".Format::rupiah($rates['sakit']);
        }

        return [
            'total' => $total,
            'fixed' => $fixed,
            'late_count' => $lateCount,
            'late_amount' => $lateAmount,
            'late_after_minutes' => $graceMinutes,
            'alpha_count' => $alphaCount,
            'alpha_amount' => $alphaAmount,
            'izin_count' => $izinCount,
            'izin_amount' => $izinAmount,
            'sakit_count' => $sakitCount,
            'sakit_amount' => $sakitAmount,
            'work_days' => $workDays,
            'summary' => $parts === [] ? '' : implode(' · ', $parts),
        ];
    }

    /**
     * Potongan telat: check-in melewati jam masuk + toleransi menit.
     */
    public static function isLateForDeduction(
        EmployeeAttendance $row,
        string $clockIn,
        int $graceMinutes = 0,
    ): bool {
        if (! filled($row->check_in)) {
            return false;
        }

        // Hanya hitung dari kehadiran (bukan alpha/izin/sakit).
        if ($row->status !== null && $row->status !== AttendanceStatus::Hadir) {
            return false;
        }

        try {
            $workDate = $row->work_date instanceof Carbon
                ? $row->work_date->toDateString()
                : (string) $row->work_date;

            $checkIn = Carbon::parse($workDate.' '.$row->check_in);
            $deadline = Carbon::parse($workDate.' '.$clockIn)->addMinutes(max(0, $graceMinutes));

            return $checkIn->greaterThan($deadline);
        } catch (\Throwable) {
            return (bool) $row->is_late && $graceMinutes === 0;
        }
    }
}
