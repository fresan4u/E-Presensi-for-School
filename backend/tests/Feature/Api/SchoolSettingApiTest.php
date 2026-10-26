<?php

namespace Tests\Feature\Api;

use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolSettingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Sebelum admin menyimpan, endpoint publik memakai nilai fallback config
     * supaya app siswa tetap bisa melakukan geofencing.
     */
    public function test_public_endpoint_returns_config_fallback(): void
    {
        config([
            'presensi.school.latitude' => -6.2,
            'presensi.school.longitude' => 106.9,
            'presensi.school.radius_meter' => 150,
        ]);

        $this->getJson('/api/v1/school-location')
            ->assertOk()
            ->assertJsonPath('data.latitude', -6.2)
            ->assertJsonPath('data.longitude', 106.9)
            ->assertJsonPath('data.radius_meter', 150);
    }

    public function test_admin_can_update_and_read_school_location(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/v1/school-location', [
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius_meter' => 200,
        ])
            ->assertOk()
            ->assertJsonPath('data.latitude', -6.175392)
            ->assertJsonPath('data.radius_meter', 200);

        $this->assertDatabaseHas('school_settings', [
            'latitude' => -6.175392,
            'radius_meter' => 200,
        ]);

        // Endpoint publik (tanpa token) langsung memakai nilai tersimpan.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/school-location')
            ->assertOk()
            ->assertJsonPath('data.latitude', -6.175392)
            ->assertJsonPath('data.longitude', 106.827153);
    }

    public function test_update_requires_panel_authentication(): void
    {
        $this->putJson('/api/v1/school-location', [
            'latitude' => -6.2,
            'longitude' => 106.9,
            'radius_meter' => 100,
        ])->assertStatus(401);

        $this->assertDatabaseCount('school_settings', 0);
    }

    public function test_update_validates_coordinates_and_radius(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/v1/school-location', [
            'latitude' => -100,
            'longitude' => 200,
            'radius_meter' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude', 'radius_meter']);

        $this->assertSame(0, SchoolSetting::query()->count());
    }
}
