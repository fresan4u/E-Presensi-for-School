<?php

namespace Tests\Feature\Api;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs(User::factory()->admin()->create());
        Student::factory()->create();
    }

    public function test_can_create_student_with_enrollment(): void
    {
        $classroom = Classroom::orderBy('id')->first();

        $payload = [
            'nis' => '8001',
            'full_name' => 'Rina Marlina',
            'classroom_id' => $classroom->id,
        ];

        $this->postJson('/api/v1/students', $payload)
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Rina Marlina')
            ->assertJsonPath('data.classroom.id', $classroom->id);

        $this->assertDatabaseHas('students', ['nis' => '8001']);
        $this->assertDatabaseHas('student_class_enrollments', ['classroom_id' => $classroom->id]);
    }

    public function test_create_student_validates_payload(): void
    {
        $this->postJson('/api/v1/students', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nis', 'full_name']);
    }

    public function test_can_search_and_filter_students(): void
    {
        $classroom = Classroom::orderBy('id')->first();
        Student::query()->update(['full_name' => 'Nama Umum']);

        $this->postJson('/api/v1/students', [
            'nis' => '9111',
            'full_name' => 'Budi Santoso Khas',
            'classroom_id' => $classroom->id,
        ])->assertCreated();

        $this->getJson('/api/v1/students?search=Budi')
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Budi Santoso Khas');

        $this->getJson("/api/v1/students?classroom_id={$classroom->id}")
            ->assertOk()
            ->assertJsonPath('data.0.classroom.id', $classroom->id);
    }

    public function test_can_update_and_delete_student(): void
    {
        $student = Student::first();
        $classroom = Classroom::orderByDesc('id')->first();

        $this->putJson("/api/v1/students/{$student->id}", [
            'full_name' => 'Nama Diubah',
            'classroom_id' => $classroom->id,
        ])->assertOk()->assertJsonPath('data.full_name', 'Nama Diubah');

        $this->deleteJson("/api/v1/students/{$student->id}")->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_can_import_students_from_csv(): void
    {
        $csv = "nis,full_name,grade_level,major,rombel,status\n"
            ."9001,Budi Santoso,X,RPL,1,aktif\n"
            ."9002,Siti Aminah,XI,DKV,2,aktif\n";

        $file = UploadedFile::fake()->createWithContent('siswa.csv', $csv);

        $this->post('/api/v1/students/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.created', 2);

        $this->assertDatabaseHas('students', ['nis' => '9001', 'full_name' => 'Budi Santoso']);
    }
}
