<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_orders', 'discount_type')) {
                $table->string('discount_type', 20)->nullable()->after('subtotal');
            }
            if (! Schema::hasColumn('pos_orders', 'discount_value')) {
                $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type');
            }
            if (! Schema::hasColumn('pos_orders', 'discount_amount')) {
                $table->decimal('discount_amount', 18, 4)->default(0)->after('discount_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            foreach (['discount_type', 'discount_value', 'discount_amount'] as $column) {
                if (Schema::hasColumn('pos_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
