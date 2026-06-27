<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cogs_calculations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 18, 6);
            $table->decimal('direct_material', 18, 4)->default(0);
            $table->decimal('direct_labor', 18, 4)->default(0);
            $table->decimal('manufacturing_overhead', 18, 4)->default(0);
            $table->decimal('total_cogs', 18, 4);
            $table->decimal('unit_cogs', 18, 4);
            $table->string('calculation_method');
            $table->json('breakdown')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cogs_calculations');
    }
};
