<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Major;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        $grades = GradeLevel::all();
        $majors = Major::all();

        foreach ($grades as $grade) {
            foreach ($majors as $major) {
                foreach ([1, 2] as $rombel) {
                    Classroom::updateOrCreate(
                        ['grade_level_id' => $grade->id, 'major_id' => $major->id, 'rombel' => $rombel],
                    );
                }
            }
        }
    }
}
