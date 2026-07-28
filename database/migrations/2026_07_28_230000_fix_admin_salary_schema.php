<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lengkapi skema HR/gaji yang sering hilang di shared hosting
 * (penyebab 500 di /admin/salaries).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'modules')) {
                    $table->json('modules')->nullable()->after('role');
                }
                if (! Schema::hasColumn('users', 'is_root')) {
                    $table->boolean('is_root')->default(false)->after('modules');
                }
                if (! Schema::hasColumn('users', 'must_change_password')) {
                    $table->boolean('must_change_password')->default(false)->after('password');
                }
            });

            foreach (DB::table('users')->select('id', 'role', 'modules')->get() as $user) {
                $modules = $user->modules;
                if ($modules === null || $modules === '' || $modules === '[]') {
                    DB::table('users')->where('id', $user->id)->update([
                        'modules' => json_encode([$user->role]),
                    ]);
                }
            }
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
                $table->decimal('daily_salary', 18, 4)->default(0);
                $table->string('status', 20)->default('active');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('pin_hash')->nullable();
                $table->timestamp('pin_set_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasColumn('employees', 'daily_salary')) {
                    $table->decimal('daily_salary', 18, 4)->default(0)->after('base_salary');
                }
                if (! Schema::hasColumn('employees', 'pin_hash')) {
                    $table->string('pin_hash')->nullable()->after('user_id');
                }
                if (! Schema::hasColumn('employees', 'pin_set_at')) {
                    $table->timestamp('pin_set_at')->nullable()->after('pin_hash');
                }
            });
        }

        if (! Schema::hasTable('employee_attendances')) {
            Schema::create('employee_attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('work_date');
                $table->time('check_in')->nullable();
                $table->decimal('check_in_lat', 10, 7)->nullable();
                $table->decimal('check_in_lng', 10, 7)->nullable();
                $table->string('check_in_photo_path')->nullable();
                $table->decimal('check_in_face_distance', 18, 6)->nullable();
                $table->time('check_out')->nullable();
                $table->decimal('check_out_lat', 10, 7)->nullable();
                $table->decimal('check_out_lng', 10, 7)->nullable();
                $table->string('check_out_photo_path')->nullable();
                $table->decimal('check_out_face_distance', 18, 6)->nullable();
                $table->string('status', 20)->default('hadir');
                $table->boolean('is_late')->default(false);
                $table->string('notes')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'work_date']);
            });
        } else {
            Schema::table('employee_attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_attendances', 'check_in_lat')) {
                    $table->decimal('check_in_lat', 10, 7)->nullable()->after('check_in');
                }
                if (! Schema::hasColumn('employee_attendances', 'check_in_lng')) {
                    $table->decimal('check_in_lng', 10, 7)->nullable()->after('check_in_lat');
                }
                if (! Schema::hasColumn('employee_attendances', 'check_in_photo_path')) {
                    $table->string('check_in_photo_path')->nullable()->after('check_in_lng');
                }
                if (! Schema::hasColumn('employee_attendances', 'check_in_face_distance')) {
                    $table->decimal('check_in_face_distance', 18, 6)->nullable()->after('check_in_photo_path');
                }
                if (! Schema::hasColumn('employee_attendances', 'check_out_lat')) {
                    $table->decimal('check_out_lat', 10, 7)->nullable()->after('check_out');
                }
                if (! Schema::hasColumn('employee_attendances', 'check_out_lng')) {
                    $table->decimal('check_out_lng', 10, 7)->nullable()->after('check_out_lat');
                }
                if (! Schema::hasColumn('employee_attendances', 'check_out_photo_path')) {
                    $table->string('check_out_photo_path')->nullable()->after('check_out_lng');
                }
                if (! Schema::hasColumn('employee_attendances', 'check_out_face_distance')) {
                    $table->decimal('check_out_face_distance', 18, 6)->nullable()->after('check_out_photo_path');
                }
                if (! Schema::hasColumn('employee_attendances', 'is_late')) {
                    $table->boolean('is_late')->default(false)->after('status');
                }
            });
        }

        if (! Schema::hasTable('employee_salaries')) {
            Schema::create('employee_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('period_month');
                $table->decimal('base_salary', 18, 4)->default(0);
                $table->decimal('daily_salary', 18, 4)->default(0);
                $table->unsignedInteger('work_days')->default(0);
                $table->decimal('allowance', 18, 4)->default(0);
                $table->decimal('deduction', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->string('status', 20)->default('draft');
                $table->dateTime('paid_at')->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'period_month']);
            });
        } else {
            Schema::table('employee_salaries', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_salaries', 'daily_salary')) {
                    $table->decimal('daily_salary', 18, 4)->default(0)->after('base_salary');
                }
                if (! Schema::hasColumn('employee_salaries', 'work_days')) {
                    $table->unsignedInteger('work_days')->default(0)->after('daily_salary');
                }
            });
        }

        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        foreach ([
            'salary_default_deduction' => '0',
            'salary_deduction_late' => '0',
            'salary_late_after_minutes' => '0',
            'salary_deduction_alpha' => '0',
            'salary_deduction_izin' => '0',
            'salary_deduction_sakit' => '0',
        ] as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if (! $exists) {
                DB::table('app_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Tidak drop — patch produksi bersifat additive.
    }
};
