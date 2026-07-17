<?php

/**
 * Package discovery aman untuk shared hosting (composer --no-dev).
 * Jangan sertakan paket require-dev (pail, pao, collision, dll).
 */
return [
    'laravel/tinker' => [
        'providers' => [
            'Laravel\\Tinker\\TinkerServiceProvider',
        ],
    ],
    'nesbot/carbon' => [
        'providers' => [
            'Carbon\\Laravel\\ServiceProvider',
        ],
    ],
];
