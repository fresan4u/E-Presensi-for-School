<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceSchedule;
use App\Models\SchoolHoliday;
use App\Models\SchoolSetting;
use App\Models\Student;
use Carbon\Carbon;
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

    /**
     * Pindahkan jam server ke hari sekolah tetap (Senin) supaya test tidak
     * bergantung pada hari saat suite dijalankan — Sabtu/Minggu default-nya
     * nonaktif di `attendance_schedules`.
     */
    private function travelToWeekday(int $hour, int $minute): void
    {
        $this->travelTo(Carbon::parse('2026-01-05')->setTime($hour, $minute));
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
        $this->travelToWeekday(6, 30);

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
        $this->travelToWeekday(16, 0);

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
        $this->travelToWeekday(9, 0);

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

        $this->travelToWeekday(7, 0);
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertCreated();

        $this->travelToWeekday(7, 1);
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

    public function test_self_scan_is_closed_on_school_holiday(): void
    {
        $this->travelToWeekday(6, 30);

        SchoolHoliday::query()->create([
            'date' => '2026-01-05',
            'name' => 'Libur Semester',
        ]);

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->loginAsStudent($student);

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertStatus(422)
            ->assertJsonPath('code', 'attendance_closed')
            ->assertJsonPath('reason', 'holiday')
            ->assertJsonPath('holiday', 'Libur Semester');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_self_scan_is_closed_on_inactive_day(): void
    {
        // Minggu default-nya nonaktif (Sabtu/Minggu = hari libur akhir pekan).
        $this->travelTo(Carbon::parse('2026-01-04')->setTime(6, 30));

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->loginAsStudent($student);

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertStatus(422)
            ->assertJsonPath('code', 'attendance_closed')
            ->assertJsonPath('reason', 'not_school_day');
    }

    public function test_self_scan_is_closed_when_admin_disables_it(): void
    {
        $this->travelToWeekday(6, 30);

        $this->putJson('/api/v1/settings', [
            'attendance' => ['self_scan_enabled' => false],
        ])->assertOk();

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->loginAsStudent($student);

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertStatus(422)
            ->assertJsonPath('code', 'attendance_closed')
            ->assertJsonPath('reason', 'disabled');
    }

    public function test_friday_uses_earlier_pulang_window(): void
    {
        // Jumat 9 Januari 2026: pulang dibuka 11:30, bukan 15:00.
        $this->travelTo(Carbon::parse('2026-01-09')->setTime(11, 29));

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->loginAsStudent($student);

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertStatus(422)->assertJsonPath('reason', 'outside_window');

        $this->travelTo(Carbon::parse('2026-01-09')->setTime(11, 30));
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertCreated()->assertJsonPath('data.session.code', 'pulang');
    }

    public function test_late_status_uses_schedule_late_after(): void
    {
        $this->travelToWeekday(6, 30);

        AttendanceSchedule::query()
            ->where('day_of_week', 1)
            ->update(['late_after' => '06:00:00']);

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->loginAsStudent($student);

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertCreated()->assertJsonPath('data.status.code', 'terlambat');
    }

    public function test_geofence_is_enforced_by_the_server(): void
    {
        $this->travelToWeekday(6, 30);

        SchoolSetting::query()->create([
            'latitude' => -6.200000,
            'longitude' => 106.900000,
            'radius_meter' => 100,
            'geofence_enabled' => true,
        ]);

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->loginAsStudent($student);

        // Tanpa koordinat -> tidak bisa diverifikasi.
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertStatus(422)->assertJsonPath('code', 'location_required');

        // Koordinat jauh di luar radius -> ditolak server.
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'lat' => -6.300000,
            'lng' => 106.900000,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'outside_school_area')
            ->assertJsonPath('radius_meter', 100);

        $this->assertDatabaseCount('attendances', 0);

        // Di dalam radius -> diterima dan jaraknya dicatat.
        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'lat' => -6.200000,
            'lng' => 106.900000,
            'accuracy' => 5.5,
        ])->assertCreated();

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'distance_meter' => 0,
        ]);
    }

    public function test_geofence_can_be_disabled_but_distance_is_still_recorded(): void
    {
        $this->travelToWeekday(6, 30);

        SchoolSetting::query()->create([
            'latitude' => -6.200000,
            'longitude' => 106.900000,
            'radius_meter' => 100,
            'geofence_enabled' => false,
        ]);

        $student = Student::first();
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", ['descriptor' => $this->descriptor(0.5)])
            ->assertCreated();
        $this->loginAsStudent($student);

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'lat' => -6.300000,
            'lng' => 106.900000,
        ])->assertCreated();

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $student->id,
            'distance_meter' => null,
        ]);
    }

    public function test_student_meta_exposes_todays_attendance_window(): void
    {
        $this->travelToWeekday(6, 30);

        $student = Student::first();
        $this->loginAsStudent($student);

        $this->getJson('/api/v1/student/meta')
            ->assertOk()
            ->assertJsonPath('data.attendance_window.day_name', 'Senin')
            ->assertJsonPath('data.attendance_window.self_cutoff', '07:00')
            ->assertJsonPath('data.attendance_window.pulang_from', '15:00')
            ->assertJsonPath('data.attendance_window.is_open', true)
            ->assertJsonPath('data.attendance_window.current_session', 'masuk');
    }
}
