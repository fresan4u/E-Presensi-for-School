<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Login dan ambil token Sanctum yang siap dipakai.
     */
    private function login(string $nis, string $pin): string
    {
        return $this->postJson('/api/v1/student/login', ['nis' => $nis, 'pin' => $pin])
            ->assertOk()
            ->json('data.token');
    }

    public function test_login_with_default_pin_returns_token_and_profile(): void
    {
        Student::factory()->create(['nis' => '2024001', 'full_name' => 'Ahmad Fauzi']);

        $response = $this->postJson('/api/v1/student/login', ['nis' => '2024001', 'pin' => '2024001'])
            ->assertOk()
            ->assertJsonPath('data.student.nis', '2024001')
            ->assertJsonPath('data.student.full_name', 'Ahmad Fauzi')
            ->assertJsonPath('data.student.must_change_pin', true);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertArrayNotHasKey('pin', $response->json('data.student'));
    }

    public function test_explicit_pin_wins_over_default(): void
    {
        Student::factory()->create(['nis' => '2024002', 'pin' => '8899']);

        $this->postJson('/api/v1/student/login', ['nis' => '2024002', 'pin' => '2024002'])
            ->assertStatus(401);

        $this->postJson('/api/v1/student/login', ['nis' => '2024002', 'pin' => '8899'])
            ->assertOk()
            ->assertJsonPath('data.student.must_change_pin', false);
    }

    public function test_login_rejects_wrong_pin(): void
    {
        Student::factory()->create(['nis' => '2024003']);

        $this->postJson('/api/v1/student/login', ['nis' => '2024003', 'pin' => 'salah'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'NIS atau PIN salah.');
    }

    public function test_login_rejects_unknown_nis(): void
    {
        $this->postJson('/api/v1/student/login', ['nis' => 'tidak-ada', 'pin' => 'apa-saja'])
            ->assertStatus(401);
    }

    public function test_login_rejects_inactive_student(): void
    {
        $inactiveId = StudentStatus::where('code', 'non_aktif')->value('id');
        Student::factory()->create(['nis' => '2024004', 'status_id' => $inactiveId]);

        $this->postJson('/api/v1/student/login', ['nis' => '2024004', 'pin' => '2024004'])
            ->assertStatus(403);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/student/me')->assertStatus(401);
    }

    public function test_me_returns_authenticated_student(): void
    {
        Student::factory()->create(['nis' => '2024005', 'full_name' => 'Budi Santoso']);
        $token = $this->login('2024005', '2024005');

        $this->withToken($token)->getJson('/api/v1/student/me')
            ->assertOk()
            ->assertJsonPath('data.nis', '2024005')
            ->assertJsonPath('data.full_name', 'Budi Santoso');
    }

    public function test_update_pin_changes_pin_and_clears_flag(): void
    {
        Student::factory()->create(['nis' => '2024006']);
        $token = $this->login('2024006', '2024006');

        $this->withToken($token)->putJson('/api/v1/student/pin', [
            'current_pin' => '2024006',
            'pin' => '4321',
            'pin_confirmation' => '4321',
        ])
            ->assertOk()
            ->assertJsonPath('data.must_change_pin', false);

        $this->postJson('/api/v1/student/login', ['nis' => '2024006', 'pin' => '2024006'])
            ->assertStatus(401);
        $this->postJson('/api/v1/student/login', ['nis' => '2024006', 'pin' => '4321'])
            ->assertOk();
    }

    public function test_update_pin_rejects_wrong_current_pin(): void
    {
        Student::factory()->create(['nis' => '2024007']);
        $token = $this->login('2024007', '2024007');

        $this->withToken($token)->putJson('/api/v1/student/pin', [
            'current_pin' => 'salah',
            'pin' => '1111',
            'pin_confirmation' => '1111',
        ])->assertStatus(422);
    }

    public function test_logout_revokes_token(): void
    {
        Student::factory()->create(['nis' => '2024008']);
        $token = $this->login('2024008', '2024008');

        $this->withToken($token)->postJson('/api/v1/student/logout')->assertOk();

        // Guard Sanctum men-cache user antar-request dalam satu proses tes;
        // lupakan guard agar token benar-benar di-resolve ulang dari header.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/student/me')->assertStatus(401);
    }
}
