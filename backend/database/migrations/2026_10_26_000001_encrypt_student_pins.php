<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * PIN siswa berubah dari hash satu arah menjadi ciphertext yang bisa dibaca
 * ulang oleh admin (panel Data Siswa). Hash lama tidak mungkin dipulihkan, jadi
 * semua PIN yang sudah ada di-reset ke NIS masing-masing dan siswa diminta
 * menggantinya lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')
            ->select(['id', 'nis'])
            ->orderBy('id')
            ->each(function (object $student): void {
                DB::table('students')
                    ->where('id', $student->id)
                    ->update([
                        'pin' => Crypt::encryptString((string) $student->nis),
                        'must_change_pin' => true,
                        'pin_changed_at' => null,
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('students')
            ->select(['id', 'pin'])
            ->orderBy('id')
            ->each(function (object $student): void {
                if (! is_string($student->pin) || $student->pin === '') {
                    return;
                }

                try {
                    $plainPin = Crypt::decryptString($student->pin);
                } catch (Throwable) {
                    return;
                }

                DB::table('students')
                    ->where('id', $student->id)
                    ->update(['pin' => Hash::make($plainPin)]);
            });
    }
};
