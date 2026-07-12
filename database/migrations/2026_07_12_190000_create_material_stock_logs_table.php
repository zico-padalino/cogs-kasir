<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_unit', 20)->nullable();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->string('action', 20);
            $table->decimal('quantity_before', 18, 6)->nullable();
            $table->decimal('quantity_after', 18, 6)->nullable();
            $table->decimal('quantity_delta', 18, 6)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('lot_number')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['product_id', 'created_at']);
            $table->index(['action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_logs');
    }
};
