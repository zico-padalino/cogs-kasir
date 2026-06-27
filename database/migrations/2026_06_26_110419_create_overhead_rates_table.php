<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overhead_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('allocation_base');
            $table->decimal('rate', 18, 6);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overhead_rates');
    }
};
