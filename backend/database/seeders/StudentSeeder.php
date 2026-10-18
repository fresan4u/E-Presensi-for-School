<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassEnrollment;
use App\Models\StudentStatus;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Satu akun siswa demo (login portal mobile memakai NIS + PIN). Siswa lain
     * dibuat lewat panel admin / API. Ditempatkan di kelas terakhir agar tidak
     * mengganggu roster kelas lain pada data uji.
     */
    public function run(): void
    {
        $config = config('presensi.student');

        $student = Student::updateOrCreate(
            ['nis' => $config['nis']],
            [
                'full_name' => $config['full_name'],
                'pin' => $config['pin'],
                'status_id' => StudentStatus::where('code', 'aktif')->value('id'),
                'must_change_pin' => false,
            ],
        );

        $yearId = AcademicYear::where('is_active', true)->value('id');
        $classroomId = Classroom::orderByDesc('id')->value('id');

        if ($yearId && $classroomId) {
            StudentClassEnrollment::updateOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $yearId],
                ['classroom_id' => $classroomId, 'is_active' => true],
            );
        }
    }
}
