<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Student::factory()->create();

        // Endpoint operasional butuh login: pakai token admin sebagai default.
        $this->withToken($this->adminToken());
    }

    /**
     * Token Sanctum admin (dari seeder) untuk endpoint operasional.
     */
    private function adminToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => config('presensi.admin.email'),
            'password' => config('presensi.admin.password'),
        ])->assertOk()->json('data.token');
    }

    private function descriptor(float $value = 0.5): array
    {
        return array_fill(0, 128, $value);
    }

    /**
     * Token Sanctum untuk siswa (PIN default = NIS).
     */
    private function tokenFor(Student $student): string
    {
        return $this->postJson('/api/v1/student/login', ['nis' => $student->nis, 'pin' => $student->nis])
            ->assertOk()
            ->json('data.token');
    }

    /**
     * Beralih ke sesi siswa: buang header admin & lupakan guard yang ter-cache
     * dari request sebelumnya, lalu pasang token siswa.
     */
    private function loginAsStudent(Student $student): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($student));
    }

    public function test_scan_matches_registered_face_and_creates_attendance(): void
    {
        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => $this->descriptor(0.5),
        ])->assertCreated();

        $response = $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated();

        $response->assertJsonPath('data.student.id', $student->id);
        $response->assertJsonPath('meta.created', true);

        $this->assertDatabaseHas('attendances', ['student_id' => $student->id]);
    }

    public function test_scan_is_idempotent_per_session(): void
    {
        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => $this->descriptor(0.5),
        ]);

        $this->postJson('/api/v1/attendance/scan', ['descriptor' => $this->descriptor(0.5), 'session' => 'masuk'])
            ->assertCreated();
        $this->postJson('/api/v1/attendance/scan', ['descriptor' => $this->descriptor(0.5), 'session' => 'masuk'])
            ->assertOk()
            ->assertJsonPath('meta.created', false);

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_scan_returns_404_for_unknown_face(): void
    {
        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => $this->descriptor(0.5),
        ]);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.9),
            'session' => 'masuk',
        ])->assertStatus(404)->assertJsonPath('code', 'face_not_recognized');
    }

    public function test_can_store_manual_attendance(): void
    {
        $student = Student::first();

        $this->postJson('/api/v1/attendances', [
            'student_id' => $student->id,
            'session' => 'masuk',
            'status' => 'izin',
            'note' => 'urusan keluarga',
        ])->assertCreated()
            ->assertJsonPath('data.status.code', 'izin')
            ->assertJsonPath('data.method.code', 'manual');
    }

    public function test_can_filter_attendances(): void
    {
        $student = Student::first();

        $this->postJson('/api/v1/attendances', [
            'student_id' => $student->id,
            'session' => 'masuk',
            'status' => 'izin',
        ])->assertCreated();

        $this->getJson('/api/v1/attendances?status=izin')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/attendances?method=manual')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/attendances?search='.urlencode($student->full_name))
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/attendances?status=hadir')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_can_filter_attendances_by_student_id(): void
    {
        $a = Student::first();
        $b = Student::factory()->create();

        $this->postJson('/api/v1/attendances', [
            'student_id' => $a->id, 'session' => 'masuk', 'status' => 'izin',
        ])->assertCreated();
        $this->postJson('/api/v1/attendances', [
            'student_id' => $b->id, 'session' => 'masuk', 'status' => 'izin',
        ])->assertCreated();

        $this->getJson("/api/v1/attendances?student_id={$a->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $a->id);
    }

    public function test_self_scan_requires_authentication(): void
    {
        $this->flushHeaders();

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertStatus(401);
    }

    public function test_self_scan_records_attendance_for_the_logged_in_student_only(): void
    {
        // Jendela absensi mandiri sesi masuk (lihat config presensi.attendance).
        $this->travelTo(now()->setTime(6, 30));

        $a = Student::factory()->create();
        $b = Student::factory()->create();

        $this->postJson("/api/v1/students/{$a->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->postJson("/api/v1/students/{$b->id}/face-descriptors", ['descriptor' => $this->descriptor(0.9)])
            ->assertCreated();

        $this->loginAsStudent($b);

        // B login tetapi memakai wajah A -> ditolak (1:1, tidak bisa titip absen).
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertStatus(404)->assertJsonPath('code', 'face_not_recognized');

        // Wajah B sendiri -> tercatat atas nama B, sesi ditentukan server (pagi = masuk).
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.9),
        ])->assertCreated()
            ->assertJsonPath('data.student.id', $b->id)
            ->assertJsonPath('data.session.code', 'masuk');

        $this->assertDatabaseHas('attendances', ['student_id' => $b->id]);
        $this->assertDatabaseMissing('attendances', ['student_id' => $a->id]);
    }

    /**
     * Sesi tidak dipilih siswa: kirim `session` apa pun diabaikan, server
     * yang menentukan dari jam.
     */
    public function test_self_scan_ignores_client_session_and_resolves_from_clock(): void
    {
        $this->travelTo(now()->setTime(16, 0));

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();

        $this->loginAsStudent($student);

        // Mengaku 'masuk' pada sore hari tetap tercatat 'pulang'.
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated()->assertJsonPath('data.session.code', 'pulang');
    }

    /**
     * Di luar jendela (antara sesi masuk & pulang) absen mandiri ditolak.
     */
    public function test_self_scan_is_rejected_outside_attendance_window(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();

        $this->loginAsStudent($student);

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertStatus(422)->assertJsonPath('code', 'attendance_closed');

        $this->assertDatabaseCount('attendances', 0);
    }

    /**
     * Tepat batas `self_cutoff` masih tercatat; lewat sedikit pun ditutup.
     */
    public function test_self_scan_window_closes_at_configured_cutoff(): void
    {
        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->loginAsStudent($student);

        $this->travelTo(now()->setTime(7, 0));
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertCreated();

        $this->travelTo(now()->setTime(7, 1));
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertStatus(422)->assertJsonPath('code', 'attendance_closed');
    }

    public function test_student_attendances_requires_authentication(): void
    {
        $this->flushHeaders();

        $this->getJson('/api/v1/student/attendances')->assertStatus(401);
    }

    public function test_student_attendances_rejects_panel_token(): void
    {
        // Token admin tetap terpasang sebagai default (lihat setUp).
        $this->getJson('/api/v1/student/attendances')->assertStatus(403);
    }

    public function test_student_attendances_only_returns_own_records(): void
    {
        $a = Student::first();
        $b = Student::factory()->create();

        $attendanceA = $this->postJson('/api/v1/attendances', [
            'student_id' => $a->id, 'session' => 'masuk', 'status' => 'izin',
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/attendances', [
            'student_id' => $b->id, 'session' => 'masuk', 'status' => 'izin',
        ])->assertCreated();

        $this->loginAsStudent($a);

        $this->getJson('/api/v1/student/attendances')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attendanceA);

        // Filter tanggal tetap menghormati kepemilikan data.
        $this->getJson('/api/v1/student/attendances?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
