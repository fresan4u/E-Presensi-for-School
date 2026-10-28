<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            // 1 = Senin, 7 = Minggu (Carbon::dayOfWeekIso).
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->boolean('is_active')->default(true);
            $table->time('masuk_start')->default('00:00:00');
            $table->time('masuk_end')->default('07:00:00');
            $table->time('handover_at')->nullable();
            $table->time('pulang_start')->default('15:00:00');
            $table->time('pulang_end')->default('23:59:59');
            $table->time('late_after')->nullable();
            $table->timestamps();
        });

        $now = now();

        // Default mengikuti perilaku lama (Senin–Kamis), Jumat pulang lebih
        // awal, dan Sabtu/Minggu nonaktif. Admin bisa mengubahnya dari panel.
        $rows = [
            ['day_of_week' => 1, 'is_active' => true, 'masuk_start' => '00:00:00', 'masuk_end' => '07:00:00', 'handover_at' => '06:40:00', 'pulang_start' => '15:00:00', 'pulang_end' => '23:59:59', 'late_after' => '07:00:00', 'created_at' => $now, 'updated_at' => $now],
            ['day_of_week' => 2, 'is_active' => true, 'masuk_start' => '00:00:00', 'masuk_end' => '07:00:00', 'handover_at' => '06:40:00', 'pulang_start' => '15:00:00', 'pulang_end' => '23:59:59', 'late_after' => '07:00:00', 'created_at' => $now, 'updated_at' => $now],
            ['day_of_week' => 3, 'is_active' => true, 'masuk_start' => '00:00:00', 'masuk_end' => '07:00:00', 'handover_at' => '06:40:00', 'pulang_start' => '15:00:00', 'pulang_end' => '23:59:59', 'late_after' => '07:00:00', 'created_at' => $now, 'updated_at' => $now],
            ['day_of_week' => 4, 'is_active' => true, 'masuk_start' => '00:00:00', 'masuk_end' => '07:00:00', 'handover_at' => '06:40:00', 'pulang_start' => '15:00:00', 'pulang_end' => '23:59:59', 'late_after' => '07:00:00', 'created_at' => $now, 'updated_at' => $now],
            ['day_of_week' => 5, 'is_active' => true, 'masuk_start' => '00:00:00', 'masuk_end' => '07:00:00', 'handover_at' => '06:40:00', 'pulang_start' => '11:30:00', 'pulang_end' => '23:59:59', 'late_after' => '07:00:00', 'created_at' => $now, 'updated_at' => $now],
            ['day_of_week' => 6, 'is_active' => false, 'masuk_start' => '00:00:00', 'masuk_end' => '07:00:00', 'handover_at' => '06:40:00', 'pulang_start' => '15:00:00', 'pulang_end' => '23:59:59', 'late_after' => '07:00:00', 'created_at' => $now, 'updated_at' => $now],
            ['day_of_week' => 7, 'is_active' => false, 'masuk_start' => '00:00:00', 'masuk_end' => '07:00:00', 'handover_at' => '06:40:00', 'pulang_start' => '15:00:00', 'pulang_end' => '23:59:59', 'late_after' => '07:00:00', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('attendance_schedules')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_schedules');
    }
};
