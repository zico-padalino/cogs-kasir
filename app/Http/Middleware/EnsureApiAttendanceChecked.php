<?php

namespace App\Http\Middleware;

use App\Services\AttendanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAttendanceChecked
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
            'api.v1.auth.*',
            'api.v1.attendance.*',
            'api.v1.kasir.pin.status',
            'api.v1.kasir.pending.poll',
            'api.v1.pesan.*',
        )) {
            return $next($request);
        }

        if ($this->attendanceService->needsProfileSetup($user)) {
            return response()->json([
                'message' => 'Lengkapi nomor telepon terlebih dahulu.',
                'code' => 'PROFILE_REQUIRED',
            ], 403);
        }

        $action = $this->attendanceService->requiredAction($user);
        if ($action === null) {
            return $next($request);
        }

        return response()->json([
            'message' => $action === 'check_out'
                ? 'Silakan absen pulang melalui scan QR di toko.'
                : 'Silakan absen masuk melalui scan QR di toko.',
            'code' => 'ATTENDANCE_REQUIRED',
            'action' => $action,
        ], 403);
    }
}
