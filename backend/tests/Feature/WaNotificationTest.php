<?php

namespace Tests\Feature;

use App\Jobs\SendWaAttendanceNotification;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\WaOutbox;
use App\Services\WaNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class WaNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Student::factory()->create();

        // Pendaftaran wajah butuh login panel: pakai token admin.
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

    /** Nyalakan fitur + secret bersama untuk seluruh test dalam satu metode. */
    private function enableWa(): void
    {
        config([
            'wa.enabled' => true,
            'wa.secret' => 'rahasia-bersama',
            'wa.url' => 'http://wa-bot.test',
        ]);
    }

    private function registerFaceFor(Student $student, float $value = 0.5): void
    {
        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => $this->descriptor($value),
        ])->assertCreated();
    }

    private function consentingGuardianFor(Student $student, string $phone = '081200000001'): Guardian
    {
        return Guardian::factory()->for($student)->create([
            'phone' => $phone,
            'is_primary' => true,
            'consent_at' => now(),
        ]);
    }

    private function photoUri(string $binary = 'bukti-foto-scan'): string
    {
        return 'data:image/jpeg;base64,'.base64_encode($binary);
    }

    public function test_scan_stores_photo_and_sends_notification_to_consent_guardian(): void
    {
        Storage::fake('local');
        $this->enableWa();
        Http::fake(['*' => Http::response(['wamid' => 'WAMID.ABC'], 200)]);

        $student = Student::first();
        $this->consentingGuardianFor($student, '081200000001');
        $this->registerFaceFor($student);

        $response = $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
            'photo' => $this->photoUri(),
        ])->assertCreated();

        $response->assertJsonPath('meta.created', true)
            ->assertJsonPath('meta.wa_queued', true);

        $attendance = Attendance::firstOrFail();
        $this->assertNotNull($attendance->photo_path);
        Storage::assertExists($attendance->photo_path);
        $this->assertSame('bukti-foto-scan', Storage::get($attendance->photo_path));

        $outbox = WaOutbox::firstOrFail();
        $this->assertSame(WaOutbox::STATUS_SENT, $outbox->status);
        $this->assertSame('6281200000001', $outbox->recipient_phone);
        $this->assertSame($attendance->photo_path, $outbox->photo_path);
        $this->assertSame('WAMID.ABC', $outbox->wamid);
        $this->assertNotNull($outbox->sent_at);

        // Isi pesan: nama siswa, sesi datang, jam, dan keterangan foto.
        $this->assertStringContainsString($student->full_name, $outbox->message);
        $this->assertStringContainsString('telah tiba di sekolah', $outbox->message);
        $this->assertStringContainsString('WIB', $outbox->message);
        $this->assertStringContainsString('Berikut foto bukti absensi.', $outbox->message);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://wa-bot.test/send'
                && $request->hasHeader('x-api-secret', 'rahasia-bersama')
                && $request['to'] === '6281200000001'
                && filled($request['text'])
                && $request['photo_base64'] === base64_encode('bukti-foto-scan');
        });
    }

    public function test_duplicate_scan_keeps_single_outbox_and_original_photo(): void
    {
        Storage::fake('local');
        $this->enableWa();
        Http::fake(['*' => Http::response(['wamid' => 'WAMID.ABC'], 200)]);

        $student = Student::first();
        $this->consentingGuardianFor($student);
        $this->registerFaceFor($student);

        $first = $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
            'photo' => $this->photoUri('foto-pertama'),
        ])->assertCreated();

        $second = $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
            'photo' => $this->photoUri('foto-kedua'),
        ])->assertOk();

        $second->assertJsonPath('meta.created', false)
            ->assertJsonPath('meta.wa_queued', false);

        // Absensi duplikat: tidak ada outbox ganda & foto asli tidak ditimpa.
        $this->assertDatabaseCount('wa_outboxes', 1);
        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::firstOrFail();
        $this->assertSame('foto-pertama', Storage::get($attendance->photo_path));

        // Foto kedua dibuang (tidak menumpuk sebagai file yatim).
        $this->assertCount(1, Storage::allFiles('attendance-photos'));

        $first->assertJsonPath('meta.wa_queued', true);
    }

    public function test_notification_skipped_when_guardian_has_no_consent(): void
    {
        $this->enableWa();
        Http::fake();

        $student = Student::first();
        Guardian::factory()->for($student)->withoutConsent()->create(['phone' => '081200000002']);
        $this->registerFaceFor($student);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated()->assertJsonPath('meta.wa_queued', false);

        $outbox = WaOutbox::firstOrFail();
        $this->assertSame(WaOutbox::STATUS_SKIPPED, $outbox->status);
        $this->assertSame('no_consent_or_phone', $outbox->skip_reason);
        $this->assertNull($outbox->recipient_phone);

        Http::assertNothingSent();
    }

    public function test_notification_skipped_for_invalid_phone(): void
    {
        $this->enableWa();
        Http::fake();

        $student = Student::first();
        $this->consentingGuardianFor($student, '123');
        $this->registerFaceFor($student);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated()->assertJsonPath('meta.wa_queued', false);

        $outbox = WaOutbox::firstOrFail();
        $this->assertSame(WaOutbox::STATUS_SKIPPED, $outbox->status);
        $this->assertSame('invalid_phone', $outbox->skip_reason);

        Http::assertNothingSent();
    }

    public function test_no_outbox_created_when_feature_disabled(): void
    {
        // WA_ENABLED=false (default phpunit.xml) — tanpa Http::fake pun tak ada panggilan.
        $student = Student::first();
        $this->consentingGuardianFor($student);
        $this->registerFaceFor($student);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated()->assertJsonPath('meta.wa_queued', false);

        $this->assertDatabaseCount('wa_outboxes', 0);
    }

    public function test_manual_attendance_queues_notification_without_photo_line(): void
    {
        $this->enableWa();
        Http::fake(['*' => Http::response(['wamid' => 'WAMID.MANUAL'], 200)]);

        $student = Student::first();
        $this->consentingGuardianFor($student);

        $this->postJson('/api/v1/attendances', [
            'student_id' => $student->id,
            'session' => 'masuk',
            'status' => 'hadir',
            'note' => 'urusan keluarga',
        ])->assertCreated();

        $outbox = WaOutbox::firstOrFail();
        $this->assertSame(WaOutbox::STATUS_SENT, $outbox->status);
        $this->assertSame('WAMID.MANUAL', $outbox->wamid);
        $this->assertStringContainsString('Catatan: urusan keluarga', $outbox->message);
        $this->assertStringContainsString('telah tiba di sekolah', $outbox->message);

        // Tanpa foto: baris keterangan foto dibuang dari pesan.
        $this->assertStringNotContainsString('Berikut foto bukti absensi.', $outbox->message);

        Http::assertSent(fn ($request) => ! array_key_exists('photo_base64', $request->data()));
    }

    public function test_update_to_existing_attendance_does_not_resend(): void
    {
        $this->enableWa();
        Http::fake();

        $student = Student::first();
        $this->consentingGuardianFor($student);

        $attendanceId = $this->postJson('/api/v1/attendances', [
            'student_id' => $student->id,
            'session' => 'masuk',
            'status' => 'hadir',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseCount('wa_outboxes', 1);

        // Ubah status absensi lama: tidak boleh memicu kiriman baru.
        $this->putJson("/api/v1/attendances/{$attendanceId}", ['status' => 'hadir'])
            ->assertOk();

        $this->assertDatabaseCount('wa_outboxes', 1);
        Http::assertSentCount(1);
    }

    public function test_http_failure_marks_failed_and_retry_command_requeues(): void
    {
        Storage::fake('local');
        $this->enableWa();
        Http::fake(['*' => Http::response('service sedang mati', 500)]);

        $student = Student::first();
        $this->consentingGuardianFor($student);
        $this->registerFaceFor($student);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated()->assertJsonPath('meta.wa_queued', false);

        $outbox = WaOutbox::firstOrFail();
        $this->assertSame(WaOutbox::STATUS_FAILED, $outbox->status);
        $this->assertSame(1, $outbox->attempts);
        $this->assertStringContainsString('500', $outbox->last_error);

        // wa:retry-failed mengantre ulang baris yang gagal.
        Queue::fake();
        $this->artisan('wa:retry-failed')->assertSuccessful();

        Queue::assertPushed(SendWaAttendanceNotification::class, 1);
        $this->assertSame(WaOutbox::STATUS_PENDING, $outbox->fresh()->status);
    }

    public function test_retry_command_is_noop_when_feature_disabled(): void
    {
        Queue::fake();

        $this->artisan('wa:retry-failed')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_notifier_failure_does_not_break_attendance_recording(): void
    {
        $this->enableWa();

        // Pipeline notifikasi meledak — absensi wajib tetap tercatat.
        $broken = Mockery::mock(WaNotifier::class);
        $broken->shouldReceive('queueFor')->andThrow(new \RuntimeException('wa-bot meledak'));
        $this->app->instance(WaNotifier::class, $broken);

        $student = Student::first();
        $this->consentingGuardianFor($student);
        $this->registerFaceFor($student);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated();

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_self_scan_stores_photo_and_queues_notification(): void
    {
        Storage::fake('local');
        $this->enableWa();
        Http::fake(['*' => Http::response(['wamid' => 'WAMID.SELF'], 200)]);

        $student = Student::first();
        $this->consentingGuardianFor($student, '081200000009');
        $this->registerFaceFor($student, 0.9);

        $login = $this->postJson('/api/v1/student/login', [
            'nis' => $student->nis,
            'pin' => $student->nis,
        ])->assertOk();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($login->json('data.token'));

        $this->postJson('/api/v1/student/attendance/scan', [
            'descriptor' => $this->descriptor(0.9),
            'session' => 'pulang',
            'photo' => $this->photoUri('foto-pulang'),
        ])->assertCreated()->assertJsonPath('meta.wa_queued', true);

        $outbox = WaOutbox::firstOrFail();
        $this->assertSame(WaOutbox::STATUS_SENT, $outbox->status);
        $this->assertSame('6281200000009', $outbox->recipient_phone);
        $this->assertStringContainsString('telah pulang dari sekolah', $outbox->message);
        $this->assertSame('foto-pulang', Storage::get($outbox->photo_path));
    }

    public function test_arrival_on_time_message_says_hadir(): void
    {
        Storage::fake('local');
        $this->enableWa();
        Http::fake(['*' => Http::response(['wamid' => 'WAMID.PAGI'], 200)]);
        $this->travelTo(now()->setTime(7, 0));

        $student = Student::first();
        $this->consentingGuardianFor($student);
        $this->registerFaceFor($student);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated()->assertJsonPath('meta.wa_queued', true);

        $this->assertSame('hadir', Attendance::firstOrFail()->status->code);

        $message = WaOutbox::firstOrFail()->message;
        $this->assertStringContainsString('telah tiba di sekolah', $message);
        $this->assertStringContainsString('Status: Hadir', $message);
    }

    /**
     * SEMENTARA (mode demo): absen melebihi batas waktu tetap tercatat `hadir`.
     * Kembalikan pengujian ini ke `terlambat` setelah logika boundary dipulihkan.
     */
    public function test_arrival_late_message_says_terlambat(): void
    {
        Storage::fake('local');
        $this->enableWa();
        Http::fake(['*' => Http::response(['wamid' => 'WAMID.SIANG'], 200)]);
        $this->travelTo(now()->setTime(8, 30));

        $student = Student::first();
        $this->consentingGuardianFor($student);
        $this->registerFaceFor($student);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'masuk',
        ])->assertCreated()->assertJsonPath('meta.wa_queued', true);

        $this->assertSame('hadir', Attendance::firstOrFail()->status->code);
        $this->assertStringContainsString('Status: Hadir', WaOutbox::firstOrFail()->message);
    }

    public function test_departure_message_has_no_status_line(): void
    {
        Storage::fake('local');
        $this->enableWa();
        Http::fake(['*' => Http::response(['wamid' => 'WAMID.PULANG'], 200)]);

        $student = Student::first();
        $this->consentingGuardianFor($student);
        $this->registerFaceFor($student);

        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => $this->descriptor(0.5),
            'session' => 'pulang',
            'photo' => $this->photoUri('foto-pulang-scan'),
        ])->assertCreated()->assertJsonPath('meta.wa_queued', true);

        $outbox = WaOutbox::firstOrFail();
        $this->assertSame(WaOutbox::STATUS_SENT, $outbox->status);
        $this->assertStringContainsString('telah pulang dari sekolah', $outbox->message);
        $this->assertStringNotContainsString('Status:', $outbox->message);
    }

    public function test_manual_entry_with_non_attendance_status_is_skipped(): void
    {
        $this->enableWa();
        Http::fake();

        $student = Student::first();
        $this->consentingGuardianFor($student);

        // Tanggal dibedakan karena absensi unik per siswa + tanggal + sesi.
        $daysAgoByStatus = ['izin' => 1, 'sakit' => 2, 'alpa' => 3];

        foreach ($daysAgoByStatus as $status => $daysAgo) {
            $this->postJson('/api/v1/attendances', [
                'student_id' => $student->id,
                'session' => 'masuk',
                'status' => $status,
                'attendance_date' => now()->subDays($daysAgo)->toDateString(),
            ])->assertCreated();
        }

        $this->assertDatabaseCount('wa_outboxes', 3);

        foreach (WaOutbox::all() as $outbox) {
            $this->assertSame(WaOutbox::STATUS_SKIPPED, $outbox->status);
            $this->assertSame('status_not_notified', $outbox->skip_reason);
            $this->assertNull($outbox->recipient_phone);
        }

        Http::assertNothingSent();
    }

    public function test_unknown_session_falls_back_to_generic_template(): void
    {
        $this->enableWa();
        Http::fake(['*' => Http::response(['wamid' => 'WAMID.GENERIK'], 200)]);

        AttendanceSession::create(['code' => 'istirahat', 'label' => 'Absen Istirahat']);

        $student = Student::first();
        $this->consentingGuardianFor($student);

        $this->postJson('/api/v1/attendances', [
            'student_id' => $student->id,
            'session' => 'istirahat',
            'status' => 'hadir',
        ])->assertCreated();

        $message = WaOutbox::firstOrFail()->message;
        $this->assertStringContainsString('informasi absensi', $message);
        $this->assertStringContainsString('Status: Hadir', $message);
    }
}
