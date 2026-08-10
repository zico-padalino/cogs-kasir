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

    /**
     * Makanan dan snack selalu masuk dapur. Nilai env dapat menambahkan
     * kategori lain tanpa sengaja menghilangkan dua kategori utama.
     */
    'kitchen_categories' => array_values(array_unique(array_merge(
        ['makanan', 'snack'],
        array_filter(array_map(
            'trim',
            explode(',', (string) env('POS_KITCHEN_CATEGORIES', '')),
        )),
    ))),

    /** Kategori cetak bar (minuman). */
    'bar_categories' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('POS_BAR_CATEGORIES', 'minuman')),
    ))),

    /**
     * Cache katalog menu jual (halaman QR /pesan + bootstrap kasir).
     * Detik. Invalidate otomatis saat produk/addon/kategori berubah.
     */
    'menu_catalog_ttl_seconds' => max(30, (int) env('POS_MENU_CACHE_TTL', 180)),

    'product_presets' => [
        'images/products/bread-loaf.svg' => 'Roti Tawar',
        'images/products/bread-pack.svg' => 'Roti Pack',
        'images/products/croissant.svg' => 'Pastry',
        'images/products/donut.svg' => 'Donat',
        'images/products/cake-slice.svg' => 'Kue Potong',
        'images/products/default-food.svg' => 'Default',
    ],

    'notifications' => [
        // Shared hosting: poll berkala = EP/NPROC penuh → 503.
        // Default: poll OFF; ambil data sekali saat push (ada pesanan baru).
        // API pending/dapur tetap mengembalikan data nyata untuk pull on-demand.
        'poll_interval_seconds' => max(30, (int) env('POS_POLL_INTERVAL', 60)),
        'auto_load_new_order' => filter_var(env('POS_AUTO_LOAD_ORDER', true), FILTER_VALIDATE_BOOL),
        // Poll berkala (interval). false = hanya pull saat push / buka layar / refresh.
        'kasir_poll_enabled' => filter_var(env('KASIR_POLL_ENABLED', false), FILTER_VALIDATE_BOOL),
        'dapur_poll_enabled' => filter_var(env('DAPUR_POLL_ENABLED', false), FILTER_VALIDATE_BOOL),
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

    /**
     * Thermal printer (ESC/POS + Thermer / mate.bluetoothprint di Android).
     * paper: 58mm (32 kolom) atau 80mm (48 kolom).
     */
    'thermal' => [
        // 58mm (POS-58) | 80mm (banyak Rongta) — untuk Thermer / ESC/POS / cetak HTML Windows
        'paper' => env('POS_THERMAL_PAPER', '58mm'),
        'thermer_play_store' => 'https://play.google.com/store/apps/details?id=mate.bluetoothprint',
        // Alias lama (kompatibilitas).
        'rawbt_play_store' => 'https://play.google.com/store/apps/details?id=mate.bluetoothprint',
    ],
];
