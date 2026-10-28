<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceSchedule;
use App\Models\SettingsAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withToken($this->adminToken());
    }

    private function adminToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => config('presensi.admin.email'),
            'password' => config('presensi.admin.password'),
        ])->assertOk()->json('data.token');
    }

    private function petugasToken(): string
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => config('presensi.petugas.email'),
            'password' => config('presensi.petugas.password'),
        ])->assertOk()->json('data.token');

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($token);

        return $token;
    }

    /**
     * Tujuh baris default dengan nilai yang bisa diubah per test.
     *
     * @return array<int, array<string, mixed>>
     */
    private function payload(): array
    {
        return AttendanceSchedule::query()
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (AttendanceSchedule $schedule): array => [
                'day_of_week' => $schedule->day_of_week,
                'is_active' => $schedule->is_active,
                'masuk_start' => substr($schedule->masuk_start, 0, 5),
                'masuk_end' => substr($schedule->masuk_end, 0, 5),
                'handover_at' => $schedule->handover_at ? substr($schedule->handover_at, 0, 5) : null,
                'pulang_start' => substr($schedule->pulang_start, 0, 5),
                'pulang_end' => substr($schedule->pulang_end, 0, 5),
                'late_after' => $schedule->late_after ? substr($schedule->late_after, 0, 5) : null,
            ])
            ->all();
    }

    public function test_admin_can_list_weekly_schedules(): void
    {
        $this->getJson('/api/v1/attendance-schedules')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.day_of_week', 1)
            ->assertJsonPath('data.0.day_name', 'Senin')
            ->assertJsonPath('data.0.masuk_end', '07:00')
            ->assertJsonPath('data.4.day_name', 'Jumat')
            ->assertJsonPath('data.4.pulang_start', '11:30')
            ->assertJsonPath('data.5.day_name', 'Sabtu')
            ->assertJsonPath('data.5.is_active', false)
            ->assertJsonPath('data.6.day_name', 'Minggu')
            ->assertJsonPath('data.6.is_active', false);
    }

    public function test_admin_can_update_all_seven_days(): void
    {
        $rows = $this->payload();

        // Jumat pulang lebih awal, Sabtu diaktifkan dengan jam berbeda.
        $rows[4]['pulang_start'] = '11:00';
        $rows[5]['is_active'] = true;
        $rows[5]['masuk_end'] = '08:00';
        $rows[5]['late_after'] = '07:30';
        $rows[5]['pulang_start'] = '13:00';

        $this->putJson('/api/v1/attendance-schedules', ['schedules' => $rows])
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.4.pulang_start', '11:00')
            ->assertJsonPath('data.5.is_active', true)
            ->assertJsonPath('data.5.masuk_end', '08:00')
            ->assertJsonPath('data.5.pulang_start', '13:00');

        $this->assertDatabaseHas('attendance_schedules', [
            'day_of_week' => 5,
            'pulang_start' => '11:00:00',
        ]);
        $this->assertDatabaseHas('attendance_schedules', [
            'day_of_week' => 6,
            'is_active' => true,
            'masuk_end' => '08:00:00',
        ]);
        $this->assertDatabaseHas('settings_audit_logs', ['subject_type' => 'attendance_schedules']);
        $this->assertSame(1, SettingsAuditLog::query()->where('subject_type', 'attendance_schedules')->count());
    }

    public function test_update_requires_exactly_seven_distinct_days(): void
    {
        $rows = $this->payload();
        $rows[1]['day_of_week'] = 1;

        $this->putJson('/api/v1/attendance-schedules', ['schedules' => $rows])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedules.1.day_of_week');

        $this->putJson('/api/v1/attendance-schedules', ['schedules' => array_slice($rows, 0, 6)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedules');
    }

    public function test_update_validates_cross_field_time_order(): void
    {
        $rows = $this->payload();

        // Selesai masuk sebelum mulai.
        $rows[0]['masuk_start'] = '07:00';
        $rows[0]['masuk_end'] = '06:00';
        // Pulang dimulai sebelum jam masuk selesai.
        $rows[1]['pulang_start'] = '06:00';
        // Handover di luar jendela masuk.
        $rows[2]['handover_at'] = '09:00';

        $this->putJson('/api/v1/attendance-schedules', ['schedules' => $rows])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'schedules.0.masuk_end',
                'schedules.1.pulang_start',
                'schedules.2.handover_at',
            ]);
    }

    public function test_petugas_cannot_read_or_update_schedules(): void
    {
        $this->petugasToken();

        $this->getJson('/api/v1/attendance-schedules')->assertForbidden();
        $this->putJson('/api/v1/attendance-schedules', ['schedules' => $this->payload()])
            ->assertForbidden();
    }
}
