<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_index_only_returns_petugas(): void
    {
        User::factory()->admin()->create(['email' => 'admin-baru@sekolah.id']);
        User::factory()->petugas()->create(['name' => 'Petugas Satu']);
        User::factory()->petugas()->create(['name' => 'Petugas Dua']);

        $data = collect($this->getJson('/api/v1/officers')->assertOk()->json('data'));

        $this->assertTrue($data->every(fn (array $user) => $user['role']['name'] === 'petugas'));
        $this->assertTrue($data->pluck('name')->contains('Petugas Satu'));
        $this->assertTrue($data->pluck('name')->contains('Petugas Dua'));
        $this->assertFalse($data->pluck('email')->contains('admin-baru@sekolah.id'));
    }

    public function test_can_create_officer_and_password_is_hidden(): void
    {
        $response = $this->postJson('/api/v1/officers', [
            'name' => 'Wahyu Petugas',
            'email' => 'wahyu@sekolah.id',
            'password' => 'rahasia123',
        ])->assertCreated();

        $this->assertSame('Wahyu Petugas', $response->json('data.name'));
        $this->assertSame('petugas', $response->json('data.role.name'));
        $this->assertArrayNotHasKey('password', $response->json('data'));

        $officer = User::where('email', 'wahyu@sekolah.id')->firstOrFail();
        $this->assertTrue(Hash::check('rahasia123', $officer->password));
        $this->assertTrue($officer->is_active);
    }

    public function test_create_validates_payload(): void
    {
        $this->postJson('/api/v1/officers', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->petugas()->create(['email' => 'sama@sekolah.id']);

        $this->postJson('/api/v1/officers', [
            'name' => 'Petugas Baru',
            'email' => 'sama@sekolah.id',
            'password' => 'rahasia123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_can_update_and_reset_password(): void
    {
        $officer = User::factory()->petugas()->create();

        $this->putJson("/api/v1/officers/{$officer->id}", [
            'name' => 'Nama Diubah',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Nama Diubah');

        $this->assertFalse($officer->fresh()->is_active);

        $this->putJson("/api/v1/officers/{$officer->id}", [
            'password' => 'sandi-baru-9',
        ])->assertOk();

        $this->assertTrue(Hash::check('sandi-baru-9', $officer->fresh()->password));
    }

    public function test_can_delete_officer(): void
    {
        $officer = User::factory()->petugas()->create();

        $this->deleteJson("/api/v1/officers/{$officer->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $officer->id]);
    }

    public function test_cannot_touch_non_officer_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->getJson("/api/v1/officers/{$admin->id}")->assertNotFound();
        $this->putJson("/api/v1/officers/{$admin->id}", ['name' => 'Hack'])->assertNotFound();
        $this->deleteJson("/api/v1/officers/{$admin->id}")->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => $admin->name]);
    }

    public function test_cannot_delete_or_deactivate_self(): void
    {
        $officer = User::factory()->petugas()->create();

        Sanctum::actingAs($officer);

        $this->deleteJson("/api/v1/officers/{$officer->id}")
            ->assertStatus(422);

        Sanctum::actingAs($officer);

        $this->putJson("/api/v1/officers/{$officer->id}", ['is_active' => false])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $officer->id]);
        $this->assertTrue($officer->fresh()->is_active);
    }
}
