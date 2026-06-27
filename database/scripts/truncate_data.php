<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = [
    'cogs_calculations',
    'sales_transactions',
    'production_order_labors',
    'production_order_materials',
    'production_orders',
    'inventory_lots',
    'bill_of_materials',
    'overhead_rates',
    'products',
];

DB::statement('SET FOREIGN_KEY_CHECKS=0');

foreach ($tables as $table) {
    DB::table($table)->truncate();
    echo "Truncated: {$table}\n";
}

DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo 'Done. products='.DB::table('products')->count().', cogs='.DB::table('cogs_calculations')->count()."\n";
