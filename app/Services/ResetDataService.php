<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDataService
{
    /** @var list<string> */
    private array $cogsTables = [
        'cogs_calculations',
        'sales_transactions',
        'pos_order_items',
        'pos_orders',
        'production_order_labors',
        'production_order_materials',
        'production_orders',
        'inventory_lots',
        'bill_of_materials',
        'overhead_rates',
        'products',
    ];

    public function resetAll(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->resetSqliteFile();

            return;
        }

        $this->deleteAllCogsData();
    }

    private function resetSqliteFile(): void
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === ':memory:' || ! is_file($database)) {
            $this->deleteAllCogsData();

            return;
        }

        DB::disconnect('sqlite');

        unlink($database);
        touch($database);

        Artisan::call('migrate', ['--force' => true]);
    }

    private function deleteAllCogsData(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            Schema::disableForeignKeyConstraints();
        }

        foreach ($this->cogsTables as $table) {
            if (DB::getDriverName() === 'sqlite') {
                DB::table($table)->delete();
                DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
            } else {
                DB::table($table)->truncate();
            }
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::enableForeignKeyConstraints();
        }
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'products' => DB::table('products')->count(),
            'overhead_rates' => DB::table('overhead_rates')->count(),
            'inventory_lots' => DB::table('inventory_lots')->count(),
            'production_orders' => DB::table('production_orders')->count(),
            'cogs_calculations' => DB::table('cogs_calculations')->count(),
        ];
    }
}
