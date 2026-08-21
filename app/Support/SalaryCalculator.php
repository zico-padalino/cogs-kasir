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
     * Hitung potongan dari setting + absensi pada rentang tanggal.
     * $waivedKeys: daftar key item yang dikecualikan (tidak dipotong), mis. "late:2026-08-05".
     *
     * @param  list<string>  $waivedKeys
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
     *     summary: string,
     *     items: list<array<string, mixed>>,
     *     waived_keys: list<string>
     * }
     */
    public static function deductionsFor(
        Employee $employee,
        Carbon $from,
        ?Carbon $to = null,
        array $waivedKeys = [],
    ): array {
        $rates = ShopSettings::salaryDeductionRates();
        $periodStart = $from->copy()->startOfDay();
        $periodEnd = ($to ?? $from->copy()->endOfMonth())->copy()->startOfDay();
        if ($periodEnd->lt($periodStart)) {
            $periodEnd = $periodStart->copy();
        }

        $fromDate = $periodStart->toDateString();
        $toDate = $periodEnd->toDateString();
        $defaultClockIn = (string) ShopSettings::get('attendance_clock_in', '08:00');
        $graceMinutes = $rates['late_after_minutes'];
        $waived = array_fill_keys(array_map('strval', $waivedKeys), true);

        $scheduleByDay = [];
        if (Schema::hasTable('employee_work_schedules')) {
            $scheduleByDay = EmployeeWorkSchedule::query()
                ->where('employee_id', $employee->id)
                ->get()
                ->keyBy('day_of_week');
        }

        $rows = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$fromDate, $toDate])
            ->orderBy('work_date')
            ->get();

        $workDays = $rows
            ->filter(fn (EmployeeAttendance $row) => $row->status === AttendanceStatus::Hadir && filled($row->check_in))
            ->count();

        $items = [];

        if ($rates['fixed'] > 0) {
            $items[] = [
                'key' => 'fixed',
                'type' => 'fixed',
                'date' => null,
                'day' => null,
                'label' => 'Potongan rutin',
                'detail' => 'Potongan tetap periode ini',
                'amount' => (float) $rates['fixed'],
                'minutes_late' => null,
            ];
        }

        foreach ($rows as $row) {
            $workDate = $row->work_date instanceof Carbon
                ? $row->work_date->copy()
                : Carbon::parse((string) $row->work_date);
            $dateStr = $workDate->toDateString();
            $dayName = $workDate->locale('id')->translatedFormat('l');
            $dateLabel = $workDate->locale('id')->translatedFormat('d M Y');

            $day = (int) $workDate->dayOfWeekIso;
            $schedule = $scheduleByDay[$day] ?? null;
            $clockIn = $schedule && ! $schedule->is_off
                ? (string) $schedule->clock_in
                : $defaultClockIn;

            if (self::isLateForDeduction($row, $clockIn, $graceMinutes) || (bool) $row->is_late) {
                $calcLate = self::isLateForDeduction($row, $clockIn, $graceMinutes);
                $minutesLate = self::minutesLate($row, $clockIn, $calcLate ? $graceMinutes : 0);
                $checkInLabel = filled($row->check_in) ? substr((string) $row->check_in, 0, 5) : '—';
                $deadlineLabel = Carbon::parse($dateStr.' '.$clockIn)
                    ->addMinutes(max(0, $graceMinutes))
                    ->format('H:i');

                $items[] = [
                    'key' => 'late:'.$dateStr,
                    'type' => 'late',
                    'date' => $dateStr,
                    'day' => $dayName,
                    'label' => "Telat · {$dayName}, {$dateLabel}",
                    'detail' => "Masuk {$checkInLabel} · batas {$deadlineLabel}"
                        .($minutesLate !== null ? " · +{$minutesLate} mnt" : ''),
                    'amount' => (float) $rates['late'],
                    'minutes_late' => $minutesLate,
                    'check_in' => $checkInLabel,
                    'deadline' => $deadlineLabel,
                ];
            }

            if ($row->status === AttendanceStatus::Alpha && $rates['alpha'] > 0) {
                $items[] = [
                    'key' => 'alpha:'.$dateStr,
                    'type' => 'alpha',
                    'date' => $dateStr,
                    'day' => $dayName,
                    'label' => "Alpha · {$dayName}, {$dateLabel}",
                    'detail' => 'Tidak hadir tanpa keterangan',
                    'amount' => (float) $rates['alpha'],
                    'minutes_late' => null,
                ];
            }

            if ($row->status === AttendanceStatus::Izin && $rates['izin'] > 0) {
                $items[] = [
                    'key' => 'izin:'.$dateStr,
                    'type' => 'izin',
                    'date' => $dateStr,
                    'day' => $dayName,
                    'label' => "Izin · {$dayName}, {$dateLabel}",
                    'detail' => 'Status izin',
                    'amount' => (float) $rates['izin'],
                    'minutes_late' => null,
                ];
            }

            if ($row->status === AttendanceStatus::Sakit && $rates['sakit'] > 0) {
                $items[] = [
                    'key' => 'sakit:'.$dateStr,
                    'type' => 'sakit',
                    'date' => $dateStr,
                    'day' => $dayName,
                    'label' => "Sakit · {$dayName}, {$dateLabel}",
                    'detail' => 'Status sakit',
                    'amount' => (float) $rates['sakit'],
                    'minutes_late' => null,
                ];
            }
        }

        $lateCount = 0;
        $alphaCount = 0;
        $izinCount = 0;
        $sakitCount = 0;
        $fixed = 0.0;
        $lateAmount = 0.0;
        $alphaAmount = 0.0;
        $izinAmount = 0.0;
        $sakitAmount = 0.0;
        $actualWaived = [];

        foreach ($items as &$item) {
            $isWaived = isset($waived[$item['key']]);
            $item['applied'] = ! $isWaived;
            if ($isWaived) {
                $actualWaived[] = $item['key'];
                continue;
            }

            $amount = (float) $item['amount'];
            switch ($item['type']) {
                case 'fixed':
                    $fixed += $amount;
                    break;
                case 'late':
                    $lateCount++;
                    $lateAmount += $amount;
                    break;
                case 'alpha':
                    $alphaCount++;
                    $alphaAmount += $amount;
                    break;
                case 'izin':
                    $izinCount++;
                    $izinAmount += $amount;
                    break;
                case 'sakit':
                    $sakitCount++;
                    $sakitAmount += $amount;
                    break;
            }
        }
        unset($item);

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
        if ($actualWaived !== []) {
            $parts[] = 'Dikecualikan '.count($actualWaived).' item';
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
            'items' => $items,
            'waived_keys' => $actualWaived,
        ];
    }

    /**
     * Rincian absensi harian untuk tabel detail (jam masuk/pulang, status, telat).
     *
     * @return list<array{
     *     date: string,
     *     day: string,
     *     date_label: string,
     *     status: string,
     *     status_label: string,
     *     check_in: string|null,
     *     check_out: string|null,
     *     schedule_in: string,
     *     schedule_out: string|null,
     *     is_late: bool,
     *     minutes_late: int|null,
     *     note: string|null
     * }>
     */
    public static function attendanceDaysFor(Employee $employee, Carbon $from, ?Carbon $to = null): array
    {
        $rates = ShopSettings::salaryDeductionRates();
        $periodStart = $from->copy()->startOfDay();
        $periodEnd = ($to ?? $from->copy()->endOfMonth())->copy()->startOfDay();
        if ($periodEnd->lt($periodStart)) {
            $periodEnd = $periodStart->copy();
        }

        $defaultClockIn = (string) ShopSettings::get('attendance_clock_in', '08:00');
        $defaultClockOut = (string) ShopSettings::get('attendance_clock_out', '17:00');
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
            ->whereBetween('work_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('work_date')
            ->get();

        $days = [];
        foreach ($rows as $row) {
            $workDate = $row->work_date instanceof Carbon
                ? $row->work_date->copy()
                : Carbon::parse((string) $row->work_date);
            $dateStr = $workDate->toDateString();
            $dayIso = (int) $workDate->dayOfWeekIso;
            $schedule = $scheduleByDay[$dayIso] ?? null;
            $clockIn = $schedule && ! $schedule->is_off
                ? (string) $schedule->clock_in
                : $defaultClockIn;
            $clockOut = $schedule && ! $schedule->is_off
                ? (string) ($schedule->clock_out ?? $defaultClockOut)
                : $defaultClockOut;

            $calcLate = self::isLateForDeduction($row, $clockIn, $graceMinutes);
            $flagLate = (bool) $row->is_late;
            $isLate = $calcLate || $flagLate;
            $minutesLate = null;
            if ($isLate) {
                $minutesLate = self::minutesLate($row, $clockIn, $calcLate ? $graceMinutes : 0);
            }
            $status = $row->status ?? AttendanceStatus::Hadir;
            $lateKey = $isLate ? 'late:'.$dateStr : null;

            $days[] = [
                'date' => $dateStr,
                'day' => $workDate->locale('id')->translatedFormat('l'),
                'date_label' => $workDate->locale('id')->translatedFormat('d M Y'),
                'status' => $status->value,
                'status_label' => $status->label(),
                'check_in' => filled($row->check_in) ? substr((string) $row->check_in, 0, 5) : null,
                'check_out' => filled($row->check_out) ? substr((string) $row->check_out, 0, 5) : null,
                'schedule_in' => substr($clockIn, 0, 5),
                'schedule_out' => substr($clockOut, 0, 5),
                'is_late' => $isLate,
                'minutes_late' => $minutesLate,
                'deduction_key' => $lateKey,
                'deduction_amount' => $isLate ? (float) $rates['late'] : 0,
                'can_toggle_potongan' => $isLate,
                'note' => filled($row->notes) ? (string) $row->notes : null,
            ];
        }

        return $days;
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

    public static function minutesLate(
        EmployeeAttendance $row,
        string $clockIn,
        int $graceMinutes = 0,
    ): ?int {
        if (! filled($row->check_in)) {
            return null;
        }

        try {
            $workDate = $row->work_date instanceof Carbon
                ? $row->work_date->toDateString()
                : (string) $row->work_date;

            $checkIn = Carbon::parse($workDate.' '.$row->check_in);
            $deadline = Carbon::parse($workDate.' '.$clockIn)->addMinutes(max(0, $graceMinutes));

            if ($checkIn->lessThanOrEqualTo($deadline)) {
                return 0;
            }

            return (int) $deadline->diffInMinutes($checkIn);
        } catch (\Throwable) {
            return null;
        }
    }
}
