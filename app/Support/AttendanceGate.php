<?php

namespace App\Support;

/**
 * Gerbang absensi wajib sebelum masuk modul (kasir/admin/cogs).
 * Matikan sementara dengan ENFORCE_BEFORE_MODULES = false.
 */
final class AttendanceGate
{
    public const ENFORCE_BEFORE_MODULES = false;

    public static function enforcesBeforeModules(): bool
    {
        return self::ENFORCE_BEFORE_MODULES;
    }
}
