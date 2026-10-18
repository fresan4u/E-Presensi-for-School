<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FaceDescriptorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs(User::factory()->admin()->create());
        Student::factory()->create();
    }

    private function descriptor(float $value = 0.5): array
    {
        return array_fill(0, 128, $value);
    }

    public function test_registers_samples_and_auto_assigns_sample_no(): void
    {
        $student = Student::first();

        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => $this->descriptor(0.5),
        ])->assertCreated()->assertJsonPath('data.sample_no', 1);

        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => $this->descriptor(0.6),
        ])->assertCreated()->assertJsonPath('data.sample_no', 2);

        $this->getJson("/api/v1/students/{$student->id}/face-descriptors")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rejects_wrong_descriptor_length(): void
    {
        $student = Student::first();

        $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => array_fill(0, 127, 0.5),
        ])->assertStatus(422)->assertJsonValidationErrors('descriptor');
    }

    public function test_can_delete_sample(): void
    {
        $student = Student::first();

        $response = $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => $this->descriptor(),
        ])->assertCreated();

        $descriptorId = $response->json('data.id');

        $this->deleteJson("/api/v1/students/{$student->id}/face-descriptors/{$descriptorId}")
            ->assertOk();

        $this->assertDatabaseMissing('student_face_descriptors', ['id' => $descriptorId]);
    }

    public function test_descriptor_vector_is_never_exposed(): void
    {
        $student = Student::first();

        $created = $this->postJson("/api/v1/students/{$student->id}/face-descriptors", [
            'descriptor' => $this->descriptor(),
        ])->assertCreated();

        $this->assertArrayNotHasKey('descriptor', $created->json('data'));

        $list = $this->getJson("/api/v1/students/{$student->id}/face-descriptors")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertArrayNotHasKey('descriptor', $list->json('data.0'));
    }
}
