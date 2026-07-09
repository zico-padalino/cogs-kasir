<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'modules')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('modules')->nullable()->after('role');
            });
        }

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'modules' => json_encode([$user->role]),
            ]);
        }

        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->string('employee_code', 32)->unique();
                $table->string('name');
                $table->string('phone', 32)->nullable();
                $table->string('email')->nullable();
                $table->string('position')->nullable();
                $table->string('department')->nullable();
                $table->date('hire_date')->nullable();
                $table->decimal('base_salary', 18, 4)->default(0);
                $table->string('status', 20)->default('active');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_attendances')) {
            Schema::create('employee_attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('work_date');
                $table->time('check_in')->nullable();
                $table->time('check_out')->nullable();
                $table->string('status', 20)->default('hadir');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'work_date']);
            });
        }

        if (! Schema::hasTable('employee_salaries')) {
            Schema::create('employee_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('period_month');
                $table->decimal('base_salary', 18, 4)->default(0);
                $table->decimal('allowance', 18, 4)->default(0);
                $table->decimal('deduction', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->string('status', 20)->default('draft');
                $table->dateTime('paid_at')->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'period_month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('employee_attendances');
        Schema::dropIfExists('employees');

        if (Schema::hasColumn('users', 'modules')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('modules');
            });
        }
    }
};
