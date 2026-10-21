<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Database\Seeder;

class GuardianSeeder extends Seeder
{
    /**
     * Wali murid untuk akun siswa demo.
     *
     * Tanpa baris ini `WaNotifier` tidak menemukan penerima (`no_consent_or_phone`)
     * sehingga notifikasi WhatsApp ke wali tidak pernah terkirim pada demo.
     * `consent_at` langsung diisi: siswa demo dianggap sudah setuju dihubungi.
     *
     * Dibuat HANYA bila siswa demo belum punya wali sama sekali. Seeder tidak
     * pernah menimpa/menambah wali yang sudah diisi lewat panel admin maupun
     * API — data sekolah selalu menang atas data demo.
     */
    public function run(): void
    {
        $config = config('presensi.student');

        $student = Student::where('nis', $config['nis'])->first();

        if ($student === null || $student->guardians()->exists()) {
            return;
        }

        Guardian::create([
            'student_id' => $student->id,
            'name' => config('presensi.guardian.name', 'Budi Santoso'),
            'relationship' => config('presensi.guardian.relationship', 'ayah'),
            'phone' => config('presensi.guardian.phone', '081234567890'),
            'is_primary' => true,
            'consent_at' => now(),
        ]);
    }
}
