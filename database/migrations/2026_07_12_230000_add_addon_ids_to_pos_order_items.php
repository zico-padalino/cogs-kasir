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
            if (! Schema::hasColumn('pos_order_items', 'addon_ids')) {
                $table->json('addon_ids')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_order_items')) {
            return;
        }

        Schema::table('pos_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('pos_order_items', 'addon_ids')) {
                $table->dropColumn('addon_ids');
            }
        });
    }
};
