<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'menu_category')) {
                $table->string('menu_category', 50)->nullable()->after('description');
            }
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_orders', 'order_type')) {
                $table->string('order_type', 20)->default('takeaway')->after('source');
            }
            if (! Schema::hasColumn('pos_orders', 'amount_received')) {
                $table->decimal('amount_received', 18, 4)->nullable()->after('total');
            }
            if (! Schema::hasColumn('pos_orders', 'change_amount')) {
                $table->decimal('change_amount', 18, 4)->nullable()->after('amount_received');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            foreach (['order_type', 'amount_received', 'change_amount'] as $column) {
                if (Schema::hasColumn('pos_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'menu_category')) {
                $table->dropColumn('menu_category');
            }
        });
    }
};
