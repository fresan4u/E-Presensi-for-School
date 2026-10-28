<?php

namespace Tests\Feature\Api;

use App\Models\AppSetting;
use App\Models\SettingsAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsApiTest extends TestCase
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

    public function test_admin_can_read_settings_and_today_window(): void
    {
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.attendance.self_scan_enabled', true)
            ->assertJsonPath('data.face.match_threshold', (float) config('face.match_threshold', 0.6))
            ->assertJsonPath('data.face.samples_per_student', 3)
            ->assertJsonPath('data.notification.wa_enabled', true)
            ->assertJsonPath('data.location.configured', false)
            ->assertJsonPath('data.location.geofence_enabled', false)
            ->assertJsonStructure([
                'data' => ['attendance', 'face', 'notification', 'location'],
                'status' => [
                    'date', 'day_of_week', 'day_name', 'self_cutoff', 'handover_at',
                    'pulang_from', 'self_scan_enabled', 'is_school_day', 'is_holiday',
                    'is_open', 'current_session', 'server_time',
                ],
            ]);
    }

    public function test_admin_can_update_settings_and_change_is_audited(): void
    {
        $this->putJson('/api/v1/settings', [
            'attendance' => ['self_scan_enabled' => false],
            'face' => [
                'match_threshold' => 0.55,
                'samples_per_student' => 5,
            ],
            'notification' => ['wa_enabled' => false],
        ])->assertOk()
            ->assertJsonPath('data.attendance.self_scan_enabled', false)
            ->assertJsonPath('data.face.match_threshold', 0.55)
            ->assertJsonPath('data.face.samples_per_student', 5)
            ->assertJsonPath('data.notification.wa_enabled', false);

        $this->assertDatabaseHas('app_settings', ['key' => 'face.match_threshold']);
        $this->assertDatabaseHas('app_settings', ['key' => 'attendance.self_scan_enabled']);

        $log = SettingsAuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('app_settings', $log->subject_type);
        $this->assertArrayHasKey('face.match_threshold', $log->changes);

        $this->getJson('/api/v1/settings/audit')
            ->assertOk()
            ->assertJsonPath('data.0.subject_type', 'app_settings')
            ->assertJsonPath('data.0.user.email', config('presensi.admin.email'));
    }

    public function test_settings_update_is_validated(): void
    {
        $this->putJson('/api/v1/settings', [
            'face' => ['match_threshold' => 0.99],
        ])->assertStatus(422)->assertJsonValidationErrors('face.match_threshold');

        $this->putJson('/api/v1/settings', [
            'face' => ['samples_per_student' => 0],
        ])->assertStatus(422)->assertJsonValidationErrors('face.samples_per_student');

        $this->putJson('/api/v1/settings', [
            'attendance' => ['self_scan_enabled' => 'bukan-boolean'],
        ])->assertStatus(422)->assertJsonValidationErrors('attendance.self_scan_enabled');

        $this->assertDatabaseCount('app_settings', 0);
    }

    public function test_petugas_cannot_read_or_change_settings(): void
    {
        $this->petugasToken();

        $this->getJson('/api/v1/settings')->assertForbidden();
        $this->putJson('/api/v1/settings', [
            'attendance' => ['self_scan_enabled' => false],
        ])->assertForbidden();

        $this->assertDatabaseCount('app_settings', 0);
    }

    public function test_school_location_is_public_but_only_admin_can_change_it(): void
    {
        $this->flushHeaders();

        $this->getJson('/api/v1/school-location')
            ->assertOk()
            ->assertJsonPath('data.configured', false);

        $this->petugasToken();
        $this->putJson('/api/v1/school-location', [
            'latitude' => -6.2,
            'longitude' => 106.9,
            'radius_meter' => 150,
            'geofence_enabled' => true,
        ])->assertForbidden();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->adminToken());

        $this->putJson('/api/v1/school-location', [
            'latitude' => -6.2,
            'longitude' => 106.9,
            'radius_meter' => 150,
            'geofence_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.latitude', -6.2)
            ->assertJsonPath('data.longitude', 106.9)
            ->assertJsonPath('data.radius_meter', 150)
            ->assertJsonPath('data.geofence_enabled', true)
            ->assertJsonPath('data.configured', true);

        $this->getJson('/api/v1/school-location')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.geofence_enabled', true);

        $this->assertDatabaseHas('settings_audit_logs', ['subject_type' => 'school_settings']);
    }

    public function test_school_location_update_is_validated(): void
    {
        $this->putJson('/api/v1/school-location', [
            'latitude' => -91,
            'longitude' => 200,
            'radius_meter' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'latitude', 'longitude', 'radius_meter',
        ]);

        $this->assertDatabaseCount('school_settings', 0);
    }

    public function test_face_threshold_and_samples_are_used_by_the_runtime(): void
    {
        $this->putJson('/api/v1/settings', [
            'face' => [
                'match_threshold' => 0.5,
                'samples_per_student' => 2,
            ],
        ])->assertOk();

        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.face.match_threshold', 0.5)
            ->assertJsonPath('data.face.samples_per_student', 2);

        $this->assertSame(0.5, app(\App\Services\SettingsService::class)->faceThreshold());
    }
}
