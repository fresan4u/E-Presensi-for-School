<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

class GradeLevelSeeder extends Seeder
{
    public function run(): void
    {
        GradeLevel::updateOrCreate(['name' => 'X'], ['level' => 10]);
        GradeLevel::updateOrCreate(['name' => 'XI'], ['level' => 11]);
        GradeLevel::updateOrCreate(['name' => 'XII'], ['level' => 12]);
    }
}
