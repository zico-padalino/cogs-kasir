<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_expenses')) {
            return;
        }

        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 18, 4);
            $table->string('category', 40);
            $table->string('payment_method', 20);
            $table->string('note');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
    }
};
