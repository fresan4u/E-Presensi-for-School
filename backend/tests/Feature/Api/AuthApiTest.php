<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminCredentials(): array
    {
        return [
            'email' => config('presensi.admin.email'),
            'password' => config('presensi.admin.password'),
        ];
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', $this->adminCredentials())
            ->assertOk()
            ->assertJsonPath('data.user.email', config('presensi.admin.email'))
            ->assertJsonPath('data.user.role.name', 'admin');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_petugas_can_login(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => config('presensi.petugas.email'),
            'password' => config('presensi.petugas.password'),
        ])
            ->assertOk()
            ->assertJsonPath('data.user.role.name', 'petugas');
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => config('presensi.admin.email'),
            'password' => 'salah',
        ])->assertStatus(401)->assertJsonPath('message', 'Email atau password salah.');
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'tidak-ada@sekolah.id',
            'password' => 'apa-saja',
        ])->assertStatus(401);
    }

    public function test_login_validates_payload(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_inactive_account(): void
    {
        $petugas = User::where('email', config('presensi.petugas.email'))->firstOrFail();
        $petugas->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $petugas->email,
            'password' => config('presensi.petugas.password'),
        ])->assertStatus(403);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $token = $this->postJson('/api/v1/auth/login', $this->adminCredentials())
            ->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', config('presensi.admin.email'))
            ->assertJsonPath('data.role.name', 'admin');
    }

    public function test_logout_revokes_token(): void
    {
        $token = $this->postJson('/api/v1/auth/login', $this->adminCredentials())
            ->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        // Lupakan guard agar token di-resolve ulang dari header.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_operational_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/students')->assertStatus(401);
        $this->getJson('/api/v1/officers')->assertStatus(401);
        $this->getJson('/api/v1/meta')->assertStatus(401);
    }

    public function test_student_token_cannot_access_panel_endpoints(): void
    {
        $token = $this->postJson('/api/v1/student/login', [
            'nis' => config('presensi.student.nis'),
            'pin' => config('presensi.student.pin'),
        ])->assertOk()->json('data.token');

        $this->withToken($token)->getJson('/api/v1/students')->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(403);
    }

    public function test_panel_token_cannot_access_student_endpoints(): void
    {
        $token = $this->postJson('/api/v1/auth/login', $this->adminCredentials())
            ->json('data.token');

        $this->withToken($token)->getJson('/api/v1/student/me')->assertStatus(403);
    }

    public function test_public_kiosk_scan_remains_open(): void
    {
        // Tanpa wajah terdaftar -> 404 (bukan 401), artinya endpoint tetap publik.
        $this->postJson('/api/v1/attendance/scan', [
            'descriptor' => array_fill(0, 128, 0.5),
            'session' => 'masuk',
        ])->assertStatus(404)->assertJsonPath('code', 'face_not_recognized');
    }

    public function test_seeder_keeps_only_one_admin(): void
    {
        User::factory()->admin()->create(['email' => 'admin-lain@sekolah.id']);

        $this->seed(UserSeeder::class);

        $adminRoleId = Role::where('name', 'admin')->value('id');

        $this->assertSame(1, User::where('role_id', $adminRoleId)->count());
        $this->assertDatabaseMissing('users', ['email' => 'admin-lain@sekolah.id']);
        $this->assertDatabaseHas('users', ['email' => config('presensi.admin.email')]);
    }
}
