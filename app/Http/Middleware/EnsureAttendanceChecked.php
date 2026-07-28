<?php

namespace App\Http\Middleware;

use App\Services\AttendanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendanceChecked
{
    /** Sementara false = absen tidak wajib sebelum masuk kasir/modul. */
    private const ENFORCE = false;

    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::ENFORCE) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs(
            'attendance.*',
            'employee.profile.*',
            'logout',
            'home',
            'login',
            'login.store',
            'password.edit',
            'password.update',
            'hub',
            'hub.switch',
            'order.*',
            'pwa.*',
            'kasir.pin.status',
            'kasir.pending.poll',
            'kasir.dapur.poll',
        )) {
            return $next($request);
        }

        if ($this->attendanceService->needsProfileSetup($user)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Lengkapi nomor telepon terlebih dahulu.',
                    'redirect' => route('employee.profile.setup'),
                ], 403);
            }

            return redirect()
                ->route('employee.profile.setup')
                ->with('error', 'Lengkapi nomor telepon dulu.');
        }

        $action = $this->attendanceService->requiredAction($user);
        if ($action === null) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $action === 'check_out'
                    ? 'Silakan absen pulang melalui scan QR.'
                    : 'Silakan absen masuk melalui scan QR.',
                'redirect' => route('attendance.scan'),
            ], 403);
        }

        // Simpan URL tujuan (kasir/admin/cogs) agar setelah absen langsung kembali.
        return redirect()
            ->guest(route('attendance.scan'))
            ->with('error', $action === 'check_out'
                ? 'Waktunya absen pulang — scan QR absensi di toko.'
                : 'Silakan absen masuk dulu lewat scan QR di toko.');
    }
}
