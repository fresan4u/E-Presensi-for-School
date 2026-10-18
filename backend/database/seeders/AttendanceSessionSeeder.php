<?php

namespace Database\Seeders;

use App\Models\AttendanceSession;
use Illuminate\Database\Seeder;

class AttendanceSessionSeeder extends Seeder
{
    public function run(): void
    {
        AttendanceSession::updateOrCreate(['code' => 'masuk'], ['label' => 'Absen Masuk', 'boundary_time' => '07:30:00']);
        AttendanceSession::updateOrCreate(['code' => 'pulang'], ['label' => 'Absen Pulang', 'boundary_time' => '15:00:00']);
    }
}
