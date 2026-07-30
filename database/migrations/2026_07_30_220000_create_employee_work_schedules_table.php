<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_work_schedules')) {
            return;
        }

        Schema::create('employee_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1=Senin ... 7=Minggu (ISO)
            $table->string('clock_in', 5)->default('08:00');
            $table->string('clock_out', 5)->default('17:00');
            $table->boolean('is_off')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_schedules');
    }
};
