<?php

namespace Database\Seeders;

use App\Models\AttendanceMethod;
use Illuminate\Database\Seeder;

class AttendanceMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            'face_recognition' => 'Face Recognition',
            'fingerprint' => 'Fingerprint',
            'manual' => 'Manual',
        ];

        foreach ($methods as $code => $label) {
            AttendanceMethod::updateOrCreate(['code' => $code], ['label' => $label]);
        }
    }
}
