<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'unit_hpp')) {
                $table->decimal('unit_hpp', 18, 4)->default(0)->after('standard_cost');
            }
            if (! Schema::hasColumn('products', 'is_menu_item')) {
                $table->boolean('is_menu_item')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('products', 'hpp_updated_at')) {
                $table->timestamp('hpp_updated_at')->nullable()->after('is_menu_item');
            }
        });

        Schema::table('cogs_calculations', function (Blueprint $table) {
            if (! Schema::hasColumn('cogs_calculations', 'total_hpp')) {
                $table->decimal('total_hpp', 18, 4)->nullable()->after('manufacturing_overhead');
            }
            if (! Schema::hasColumn('cogs_calculations', 'unit_hpp')) {
                $table->decimal('unit_hpp', 18, 4)->nullable()->after('total_hpp');
            }
        });

        DB::table('cogs_calculations')
            ->whereNull('total_hpp')
            ->update([
                'total_hpp' => DB::raw('total_cogs'),
                'unit_hpp' => DB::raw('unit_cogs'),
            ]);

        DB::table('products')
            ->where('unit_hpp', 0)
            ->where('standard_cost', '>', 0)
            ->update([
                'unit_hpp' => DB::raw('standard_cost'),
            ]);

        DB::table('products')
            ->whereIn('type', ['finished_good', 'semi_finished'])
            ->update(['is_menu_item' => true]);
    }

    public function down(): void
    {
        Schema::table('cogs_calculations', function (Blueprint $table) {
            $table->dropColumn(['total_hpp', 'unit_hpp']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unit_hpp', 'is_menu_item', 'hpp_updated_at']);
        });
    }
};
