<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicYearApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs($this->createAdmin());
    }

    private function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function createYear(array $overrides = [], string $label): AcademicYear
    {
        $data = array_merge([
            'label' => $label,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
        ], $overrides);

        return AcademicYear::create($data);
    }

    public function test_can_list_all_academic_years_with_start_and_end_date(): void
    {
        $this->createYear([], '2050/2051');
        $this->createYear([], '2051/2052');

        $response = $this->getJson('/api/v1/academic-years');

        $response->assertOk();
        $labels = collect($response->json('data'))->pluck('label')->toArray();
        $this->assertContains('2050/2051', $labels);
        $this->assertContains('2051/2052', $labels);

        $latest = $response->json('data')[0];
        $this->assertArrayHasKey('start_date', $latest);
        $this->assertArrayHasKey('end_date', $latest);
        $this->assertArrayHasKey('is_active', $latest);
    }

    public function test_can_store_new_academic_year(): void
    {
        $response = $this->postJson('/api/v1/academic-years', [
            'label' => '2028/2029',
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.label', '2028/2029')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('academic_years', [
            'label' => '2028/2029',
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
            'is_active' => true,
        ]);
    }

    public function test_cannot_create_year_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/academic-years', [
            'label' => '',
            'start_date' => '2027-07-01',
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_update_academic_year(): void
    {
        $year = $this->createYear([], '2052/2053');

        $response = $this->putJson("/api/v1/academic-years/{$year->id}", [
            'label' => '2052/2054',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.label', '2052/2054');

        $this->assertDatabaseHas('academic_years', [
            'id' => $year->id,
            'label' => '2052/2054',
        ]);
    }

    public function test_activate_deactivates_other_years(): void
    {
        $new = $this->createYear([], '2051/2052');
        $existing = $this->createYear([], '2054/2055');

        $response = $this->patchJson("/api/v1/academic-years/{$new->id}/activate", []);

        $response->assertOk();

        $this->assertDatabaseHas('academic_years', [
            'id' => $new->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('academic_years', [
            'id' => $existing->id,
            'is_active' => true,
        ]);
    }

    public function test_cannot_delete_active_year(): void
    {
        $year = AcademicYear::where('is_active', true)->firstOrFail();

        $response = $this->deleteJson("/api/v1/academic-years/{$year->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Tahun ajaran aktif tidak boleh dihapus. Aktifkan tahun lain terlebih dahulu.');

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_can_delete_inactive_year_without_enrollments(): void
    {
        $year = $this->createYear([], '2055/2056');

        $response = $this->deleteJson("/api/v1/academic-years/{$year->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
    }

    public function test_cannot_delete_year_with_enrollments(): void
    {
        $year = $this->createYear([], '2056/2057');
        StudentClassEnrollment::create([
            'student_id' => Student::factory()->create()->id,
            'classroom_id' => Classroom::first()->id,
            'academic_year_id' => $year->id,
        ]);

        $response = $this->deleteJson("/api/v1/academic-years/{$year->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Tahun ajaran sudah dipakai data siswa atau absensi sehingga tidak bisa dihapus.');
    }
}
