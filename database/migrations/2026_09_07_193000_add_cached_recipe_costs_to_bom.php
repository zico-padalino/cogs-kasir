<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('bill_of_materials', 'unit_cost')) {
                $table->decimal('unit_cost', 18, 4)->default(0)->after('sequence');
            }
            if (! Schema::hasColumn('bill_of_materials', 'line_cost')) {
                $table->decimal('line_cost', 18, 4)->default(0)->after('unit_cost');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'recipe_material_cost')) {
                $table->decimal('recipe_material_cost', 18, 4)->default(0)->after('unit_hpp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('bill_of_materials', 'unit_cost') ? 'unit_cost' : null,
                Schema::hasColumn('bill_of_materials', 'line_cost') ? 'line_cost' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'recipe_material_cost')) {
                $table->dropColumn('recipe_material_cost');
            }
        });
    }
};
