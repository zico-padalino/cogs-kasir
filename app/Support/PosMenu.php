<?php

namespace App\Support;

class PosMenu
{
    public static function orderUrl(): string
    {
        return url(route('order.menu', absolute: false));
    }
}
