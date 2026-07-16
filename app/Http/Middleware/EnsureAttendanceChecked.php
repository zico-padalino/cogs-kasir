<?php

namespace App\Http\Middleware;

use App\Services\AttendanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendanceChecked
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
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
        )) {
            return $next($request);
        }

        if ($this->attendanceService->needsProfileSetup($user)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Lengkapi data karyawan dan daftar wajah terlebih dahulu.',
                    'redirect' => route('employee.profile.setup'),
                ], 403);
            }

            return redirect()
                ->route('employee.profile.setup')
                ->with('error', 'Lengkapi nomor telepon dan daftarkan wajah dulu.');
        }

        $action = $this->attendanceService->requiredAction($user);
        if ($action === null) {
            return $next($request);
        }

        $route = $action === 'check_out' ? 'attendance.check-out' : 'attendance.check-in';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $action === 'check_out'
                    ? 'Silakan absen pulang terlebih dahulu.'
                    : 'Silakan absen masuk terlebih dahulu.',
                'redirect' => route($route),
            ], 403);
        }

        return redirect()
            ->route($route)
            ->with('error', $action === 'check_out'
                ? 'Waktunya absen pulang. Ambil foto wajah dan lokasi dulu.'
                : 'Silakan absen masuk dulu (foto wajah + lokasi) sebelum lanjut.');
    }
}
