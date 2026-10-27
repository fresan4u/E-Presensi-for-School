<?php

namespace Tests\Feature\Api;

use App\Models\Classroom;
use App\Models\Guardian;
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

    /**
     * Wali murid ikut dibuat saat siswa baru dan tampil di resource.
     * `consent_at` langsung diisi supaya nomor siap dipakai notifikasi WA.
     */
    public function test_can_create_student_with_guardian(): void
    {
        $this->postJson('/api/v1/students', [
            'nis' => '8002',
            'full_name' => 'Rina Marlina',
            'guardians' => [
                ['name' => 'Ahmad', 'relationship' => 'ayah', 'phone' => '08111111111'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.guardians.0.phone', '08111111111')
            ->assertJsonPath('data.guardians.0.is_primary', true);

        $guardian = Guardian::query()->whereHas('student', fn ($q) => $q->where('nis', '8002'))->firstOrFail();
        $this->assertNotNull($guardian->consent_at);
    }

    /**
     * Kirim `guardians` berarti ganti-daftar; tanpa field itu wali lama tetap
     * utuh (update NIS/nama tidak boleh menghapus nomor wali).
     */
    public function test_guardian_list_is_replaced_only_when_submitted(): void
    {
        $student = Student::first();
        $student->guardians()->create([
            'name' => 'Lama', 'relationship' => 'ibu', 'phone' => '08222222222', 'consent_at' => now(),
        ]);

        // Tanpa field guardians -> tidak disentuh.
        $this->putJson("/api/v1/students/{$student->id}", ['full_name' => 'Nama Baru'])
            ->assertOk();
        $this->assertDatabaseHas('guardians', ['student_id' => $student->id, 'phone' => '08222222222']);

        // Dengan field guardians -> daftar lama diganti seluruhnya.
        $this->putJson("/api/v1/students/{$student->id}", [
            'full_name' => 'Nama Baru',
            'guardians' => [
                ['name' => 'Baru', 'relationship' => 'ayah', 'phone' => '08333333333'],
            ],
        ])->assertOk()->assertJsonPath('data.guardians.0.phone', '08333333333');

        $this->assertDatabaseMissing('guardians', ['phone' => '08222222222']);
        $this->assertDatabaseHas('guardians', ['student_id' => $student->id, 'phone' => '08333333333']);
    }

    /**
     * Impor CSV membaca kolom wali opsional.
     */
    public function test_import_reads_optional_guardian_columns(): void
    {
        $csv = "nis,full_name,guardian_name,guardian_relationship,guardian_phone\n"
            ."9101,Siti Aminah,Siti Rahma,ibu,08555555555\n"
            ."9102,Andi Wijaya,,,,\n";

        $file = UploadedFile::fake()->createWithContent('wali.csv', $csv);

        $this->post('/api/v1/students/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertDatabaseHas('guardians', ['name' => 'Siti Rahma', 'phone' => '08555555555']);
        // Baris tanpa nomor wali tidak membuat baris guardian kosong.
        $this->assertDatabaseMissing('guardians', ['student_id' => Student::where('nis', '9102')->firstOrFail()->id]);
    }

    /**
     * Admin harus bisa melihat PIN setiap siswa untuk dibagikan/diingatkan,
     * sedangkan endpoint publik tidak pernah membocorkannya.
     */
    public function test_admin_can_see_student_pin(): void
    {
        $student = Student::firstOrFail();

        $this->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('data.0.pin', $student->nis);
    }

    public function test_student_pin_is_not_returned_to_anonymous_requests(): void
    {
        // Lepas sesi Sanctum dari setUp agar request benar-benar anonim.
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/students');

        $response->assertUnauthorized();
        $this->assertArrayNotHasKey('pin', (array) $response->json('data.0', []));
    }

    public function test_admin_can_create_student_with_explicit_pin(): void
    {
        $this->postJson('/api/v1/students', [
            'nis' => '8003',
            'full_name' => 'Siswa Pin Baru',
            'pin' => '889900',
        ])
            ->assertCreated()
            ->assertJsonPath('data.pin', '889900');

        $this->assertSame('889900', Student::where('nis', '8003')->firstOrFail()->pin);
    }

    /**
     * Reset PIN oleh admin membuka lagi kesempatan siswa mengganti PIN sendiri.
     */
    public function test_admin_can_change_student_pin_and_reopens_pin_change(): void
    {
        $student = Student::firstOrFail();
        $student->update(['pin_changed_at' => now()]);

        $this->putJson("/api/v1/students/{$student->id}", ['pin' => '7766'])
            ->assertOk()
            ->assertJsonPath('data.pin', '7766');

        $student->refresh();
        $this->assertSame('7766', $student->pin);
        $this->assertFalse($student->must_change_pin);
        $this->assertNull($student->pin_changed_at);
    }
}
