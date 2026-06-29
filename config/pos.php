<?php

return [
    'shop_name' => env('POS_SHOP_NAME', 'Coffee & Kitchen'),

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
];
