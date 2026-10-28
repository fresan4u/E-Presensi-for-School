<?php

namespace Tests\Feature\Api;

use App\Models\SchoolHoliday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolHolidayApiTest extends TestCase
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

    public function test_admin_can_create_list_update_and_delete_holidays(): void
    {
        $created = $this->postJson('/api/v1/school-holidays', [
            'date' => '2026-01-05',
            'name' => 'Libur Semester',
            'description' => 'Libur akhir semester ganjil',
        ])->assertCreated()
            ->assertJsonPath('data.date', '2026-01-05')
            ->assertJsonPath('data.name', 'Libur Semester')
            ->json('data');

        $this->postJson('/api/v1/school-holidays', [
            'date' => '2026-12-25',
            'name' => 'Natal',
        ])->assertCreated();

        $this->getJson('/api/v1/school-holidays?year=2026')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-01-05')
            ->assertJsonPath('data.1.date', '2026-12-25');

        // Tahun lain tidak ikut terbawa.
        $this->getJson('/api/v1/school-holidays?year=2027')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->putJson("/api/v1/school-holidays/{$created['id']}", [
            'date' => '2026-01-06',
            'name' => 'Libur Semester (diperpanjang)',
        ])->assertOk()
            ->assertJsonPath('data.date', '2026-01-06')
            ->assertJsonPath('data.name', 'Libur Semester (diperpanjang)');

        $this->deleteJson("/api/v1/school-holidays/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('message', 'Hari libur dihapus.');

        $this->assertDatabaseMissing('school_holidays', ['id' => $created['id']]);
        $this->assertSame(4, \App\Models\SettingsAuditLog::query()
            ->where('subject_type', 'school_holidays')
            ->count());
    }

    public function test_holiday_date_must_be_unique_and_valid(): void
    {
        SchoolHoliday::query()->create([
            'date' => '2026-01-05',
            'name' => 'Libur Semester',
        ]);

        $this->postJson('/api/v1/school-holidays', [
            'date' => '2026-01-05',
            'name' => 'Duplikat',
        ])->assertStatus(422)->assertJsonValidationErrors('date');

        $this->postJson('/api/v1/school-holidays', [
            'date' => '05-01-2026',
            'name' => 'Format salah',
        ])->assertStatus(422)->assertJsonValidationErrors('date');

        $this->postJson('/api/v1/school-holidays', [
            'date' => '2026-01-07',
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_updating_holiday_keeps_its_own_date_valid(): void
    {
        $holiday = SchoolHoliday::query()->create([
            'date' => '2026-01-05',
            'name' => 'Libur Semester',
        ]);

        $this->putJson("/api/v1/school-holidays/{$holiday->id}", [
            'date' => '2026-01-05',
            'name' => 'Libur Semester',
            'description' => 'Tetap di tanggal yang sama',
        ])->assertOk();

        $this->assertDatabaseHas('school_holidays', [
            'id' => $holiday->id,
            'description' => 'Tetap di tanggal yang sama',
        ]);
    }

    public function test_petugas_cannot_manage_holidays(): void
    {
        $holiday = SchoolHoliday::query()->create([
            'date' => '2026-01-05',
            'name' => 'Libur Semester',
        ]);

        $this->petugasToken();

        $this->getJson('/api/v1/school-holidays')->assertForbidden();
        $this->postJson('/api/v1/school-holidays', [
            'date' => '2026-02-01',
            'name' => 'Libur',
        ])->assertForbidden();
        $this->putJson("/api/v1/school-holidays/{$holiday->id}", [
            'date' => '2026-01-06',
            'name' => 'Diubah petugas',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/school-holidays/{$holiday->id}")->assertForbidden();

        $this->assertDatabaseHas('school_holidays', [
            'id' => $holiday->id,
            'date' => '2026-01-05',
            'name' => 'Libur Semester',
        ]);
    }
}
