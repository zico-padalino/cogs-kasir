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
        'poll_interval_seconds' => (int) env('POS_POLL_INTERVAL', 12),
        'auto_load_new_order' => filter_var(env('POS_AUTO_LOAD_ORDER', true), FILTER_VALIDATE_BOOL),
    ],

    'pwa' => [
        'theme_color' => '#4f46e5',
        'background_color' => '#f1f5f9',
    ],

    /** Password awal saat admin membuat akun baru (user bisa ubah sendiri lewat Ubah Password). */
    'default_user_password' => env('DEFAULT_USER_PASSWORD', 'password'),
];
