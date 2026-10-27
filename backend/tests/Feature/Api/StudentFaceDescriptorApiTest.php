<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentFaceDescriptorApiTest extends TestCase
{
    use RefreshDatabase;

    private function descriptor(float $value = 0.5): array
    {
        return array_fill(0, 128, $value);
    }

    public function test_student_can_register_and_list_own_face_samples(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/face-descriptors', [
            'descriptor' => $this->descriptor(0.5),
        ])->assertCreated()->assertJsonPath('data.sample_no', 1);

        $this->postJson('/api/v1/student/face-descriptors', [
            'descriptor' => $this->descriptor(0.6),
        ])->assertCreated()->assertJsonPath('data.sample_no', 2);

        $this->getJson('/api/v1/student/face-descriptors')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.descriptor');
    }

    public function test_student_can_only_delete_own_samples(): void
    {
        $owner = Student::factory()->create();
        $other = Student::factory()->create();

        Sanctum::actingAs($owner);
        $created = $this->postJson('/api/v1/student/face-descriptors', [
            'descriptor' => $this->descriptor(),
        ])->assertCreated();

        $descriptorId = $created->json('data.id');

        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/student/face-descriptors/{$descriptorId}")
            ->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/student/face-descriptors/{$descriptorId}")
            ->assertOk();
    }

    public function test_officer_cannot_use_student_registration_endpoint(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/student/face-descriptors', [
            'descriptor' => $this->descriptor(),
            'student_id' => $student->id,
        ])->assertForbidden();
    }

    public function test_rejects_wrong_descriptor_length(): void
    {
        Sanctum::actingAs(Student::factory()->create());

        $this->postJson('/api/v1/student/face-descriptors', [
            'descriptor' => array_fill(0, 127, 0.5),
        ])->assertStatus(422)->assertJsonValidationErrors('descriptor');
    }
}
