<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_order_items')) {
            return;
        }

        Schema::table('pos_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_order_items', 'is_delivered')) {
                $table->boolean('is_delivered')->default(false)->after('addon_ids');
            }
            if (! Schema::hasColumn('pos_order_items', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('is_delivered');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_order_items')) {
            return;
        }

        Schema::table('pos_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('pos_order_items', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('pos_order_items', 'is_delivered')) {
                $table->dropColumn('is_delivered');
            }
        });
    }
};
