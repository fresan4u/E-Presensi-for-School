<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferenceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_meta_endpoint_returns_lookup_and_face_config(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.face.descriptor_length', 128)
            ->assertJsonPath('data.face.samples_per_student', 3)
            ->assertJsonCount(5, 'data.attendance_statuses');
    }

    public function test_classrooms_include_composed_name(): void
    {
        $this->getJson('/api/v1/classrooms')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'X RPL 1');
    }
}
