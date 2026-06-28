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
];
