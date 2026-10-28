<?php

namespace Database\Seeders;

use App\Models\AttendanceSchedule;
use Illuminate\Database\Seeder;

class AttendanceScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['day_of_week' => 1, 'is_active' => true, 'pulang_start' => '15:00:00'],
            ['day_of_week' => 2, 'is_active' => true, 'pulang_start' => '15:00:00'],
            ['day_of_week' => 3, 'is_active' => true, 'pulang_start' => '15:00:00'],
            ['day_of_week' => 4, 'is_active' => true, 'pulang_start' => '15:00:00'],
            ['day_of_week' => 5, 'is_active' => true, 'pulang_start' => '11:30:00'],
            ['day_of_week' => 6, 'is_active' => false, 'pulang_start' => '15:00:00'],
            ['day_of_week' => 7, 'is_active' => false, 'pulang_start' => '15:00:00'],
        ];

        foreach ($defaults as $row) {
            AttendanceSchedule::query()->updateOrCreate(
                ['day_of_week' => $row['day_of_week']],
                [
                    'is_active' => $row['is_active'],
                    'masuk_start' => '00:00:00',
                    'masuk_end' => '07:00:00',
                    'handover_at' => '06:40:00',
                    'pulang_start' => $row['pulang_start'],
                    'pulang_end' => '23:59:59',
                    'late_after' => '07:00:00',
                ],
            );
        }
    }
}
