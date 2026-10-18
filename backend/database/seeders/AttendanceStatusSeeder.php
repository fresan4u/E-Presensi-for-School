<?php

namespace Database\Seeders;

use App\Models\AttendanceStatus;
use Illuminate\Database\Seeder;

class AttendanceStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'hadir' => 'Hadir',
            'terlambat' => 'Terlambat',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alpa' => 'Alpa',
        ];

        foreach ($statuses as $code => $label) {
            AttendanceStatus::updateOrCreate(['code' => $code], ['label' => $label]);
        }
    }
}
