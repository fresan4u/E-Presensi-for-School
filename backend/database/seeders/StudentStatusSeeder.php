<?php

namespace Database\Seeders;

use App\Models\StudentStatus;
use Illuminate\Database\Seeder;

class StudentStatusSeeder extends Seeder
{
    public function run(): void
    {
        StudentStatus::updateOrCreate(['code' => 'aktif'], ['label' => 'Aktif']);
        StudentStatus::updateOrCreate(['code' => 'non_aktif'], ['label' => 'Non Aktif']);
    }
}
