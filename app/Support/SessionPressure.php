<?php

namespace App\Support;

/**
 * Bantu kurangi 503 di shared hosting: lepas session lock lebih awal
 * supaya request poll bersamaan tidak mengantri satu file/row session.
 */
final class SessionPressure
{
    public static function releaseEarly(): void
    {
        if (! app()->bound('session') || ! session()->isStarted()) {
            return;
        }

        session()->save();
    }
}
