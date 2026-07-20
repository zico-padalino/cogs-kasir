<?php

return [
    'shop_name' => env('POS_SHOP_NAME', 'Coffee & Kitchen'),
    'shop_title' => env('POS_SHOP_TITLE', 'Menu & pesanan dari HP'),
    'logo_path' => null,

    // Default seed only — runtime categories live in menu_categories table.
    'menu_categories' => [
        'minuman' => 'Minuman',
        'makanan' => 'Makanan',
        'pastry' => 'Pastry',
        'snack' => 'Snack',
        'lainnya' => 'Lainnya',
    ],

    'product_presets' => [
        'images/products/bread-loaf.svg' => 'Roti Tawar',
        'images/products/bread-pack.svg' => 'Roti Pack',
        'images/products/croissant.svg' => 'Pastry',
        'images/products/donut.svg' => 'Donat',
        'images/products/cake-slice.svg' => 'Kue Potong',
        'images/products/default-food.svg' => 'Default',
    ],

    'notifications' => [
        'poll_interval_seconds' => (int) env('POS_POLL_INTERVAL', 5),
        'auto_load_new_order' => filter_var(env('POS_AUTO_LOAD_ORDER', true), FILTER_VALIDATE_BOOL),
    ],

    /**
     * Push saat app/browser tertutup.
     * APK: FCM langsung (storage/app/firebase/service-account.json).
     * Expo Go: Expo Push. Web: `php artisan kasir:vapid-keys`.
     */
    'push' => [
        'enabled' => filter_var(env('KASIR_PUSH_ENABLED', true), FILTER_VALIDATE_BOOL),
        'vapid_public_key' => env('VAPID_PUBLIC_KEY'),
        'vapid_private_pem' => str_replace('\\n', "\n", (string) env('VAPID_PRIVATE_PEM', '')),
        'vapid_subject' => env('VAPID_SUBJECT', 'mailto:admin@kedaitjoan.online'),
        'firebase_credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'pwa' => [
        'theme_color' => '#5c4033',
        'background_color' => '#f6f1ea',
    ],

    /** Password awal saat admin membuat akun baru (user bisa ubah sendiri lewat Ubah Password). */
    'default_user_password' => env('DEFAULT_USER_PASSWORD', 'password'),

    /** Berapa menit sesi PIN kasir berlaku sebelum harus dimasukkan lagi. */
    'kasir_pin_ttl_minutes' => (int) env('KASIR_PIN_TTL_MINUTES', 10),
];
