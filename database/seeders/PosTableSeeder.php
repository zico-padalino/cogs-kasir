<?php

namespace Database\Seeders;

use App\Models\PosTable;
use Illuminate\Database\Seeder;

class PosTableSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            ['table_number' => '01', 'label' => 'Meja 1'],
            ['table_number' => '02', 'label' => 'Meja 2'],
            ['table_number' => '03', 'label' => 'Meja 3'],
            ['table_number' => '04', 'label' => 'Meja 4'],
            ['table_number' => '05', 'label' => 'Meja 5'],
        ];

        foreach ($tables as $table) {
            PosTable::updateOrCreate(
                ['table_number' => $table['table_number']],
                ['label' => $table['label'], 'is_active' => true],
            );
        }
    }
}
