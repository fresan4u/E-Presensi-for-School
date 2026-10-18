<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            StudentStatusSeeder::class,
            AttendanceStatusSeeder::class,
            AttendanceMethodSeeder::class,
            AttendanceSessionSeeder::class,
            AcademicYearSeeder::class,
            GradeLevelSeeder::class,
            MajorSeeder::class,
            ClassroomSeeder::class,
            UserSeeder::class,
            StudentSeeder::class,
        ]);
    }
}
