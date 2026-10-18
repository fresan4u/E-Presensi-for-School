<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceMethod;
use App\Models\AttendanceSession;
use App\Models\AttendanceStatus;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentClassEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecapTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->classroom = Classroom::orderBy('id')->firstOrFail();
        $this->academicYear = AcademicYear::where('is_active', true)->firstOrFail();
    }

    public function test_recap_returns_full_roster_with_alpa_default_and_guarded_contacts(): void
    {
        // Siswa dengan wali yang sudah memberi izin.
        $consented = Student::factory()->create(['full_name' => 'Ani Berizin']);
        Guardian::factory()->for($consented)->create([
            'name' => 'Bapak Ani',
            'relationship' => 'ayah',
            'phone' => '081200000001',
            'is_primary' => true,
            'consent_at' => now(),
        ]);
        $this->enroll($consented);

        // Siswa dengan wali yang belum memberi izin.
        $notConsented = Student::factory()->create(['full_name' => 'Budi Belum']);
        Guardian::factory()->for($notConsented)->withoutConsent()->create([
            'phone' => '081200000002',
        ]);
        $this->enroll($notConsented);

        // Siswa di kelas lain tidak boleh ikut terhitung.
        $otherClassroom = Classroom::whereKeyNot($this->classroom->id)->orderBy('id')->firstOrFail();
        $otherStudent = Student::factory()->create();
        StudentClassEnrollment::create([
            'student_id' => $otherStudent->id,
            'classroom_id' => $otherClassroom->id,
            'academic_year_id' => $this->academicYear->id,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/v1/attendances/recap?classroom_id={$this->classroom->id}&date=2026-10-13",
        );

        $response->assertOk()
            ->assertJsonPath('data.classroom.name', $this->classroom->name)
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.alpa', 2)
            ->assertJsonCount(2, 'data.students');

        $students = collect($response->json('data.students'))->keyBy('full_name');

        // Tanpa baris absensi, status turunan adalah alpa.
        $this->assertSame('alpa', $students['Ani Berizin']['status']['code']);

        // Kontak hanya diberikan bila ada consent.
        $this->assertSame('081200000001', $students['Ani Berizin']['guardian']['phone']);
        $this->assertNull($students['Budi Belum']['guardian']);

        // Roster kelas lain tidak ikut.
        $this->assertFalse($students->has($otherStudent->full_name));
    }

    public function test_recap_reflects_recorded_attendance_status(): void
    {
        $student = Student::factory()->create(['full_name' => 'Citra Hadir']);
        $this->enroll($student);

        Attendance::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'attendance_date' => '2026-10-13',
            'session_id' => AttendanceSession::where('code', 'masuk')->value('id'),
            'status_id' => AttendanceStatus::where('code', 'hadir')->value('id'),
            'method_id' => AttendanceMethod::where('code', 'manual')->value('id'),
            'checked_at' => now(),
        ]);

        $this->getJson("/api/v1/attendances/recap?classroom_id={$this->classroom->id}&date=2026-10-13")
            ->assertOk()
            ->assertJsonPath('data.summary.hadir', 1)
            ->assertJsonPath('data.summary.alpa', 0)
            ->assertJsonPath('data.students.0.status.code', 'hadir');
    }

    public function test_recap_validates_classroom_id_and_unknown_classroom(): void
    {
        $this->getJson('/api/v1/attendances/recap')
            ->assertStatus(422);

        $this->getJson('/api/v1/attendances/recap?classroom_id=999999')
            ->assertNotFound();
    }

    private function enroll(Student $student): void
    {
        StudentClassEnrollment::create([
            'student_id' => $student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'is_active' => true,
        ]);
    }
}
