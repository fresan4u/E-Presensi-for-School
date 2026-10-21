<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `biometric_consent_at`: izin wali untuk pemrosesan data biometrik anaknya.
 *
 * Dipisah dari `consent_at` yang sudah ada — kolom itu artinya izin
 * DIHUBUNGI lewat WhatsApp (dipakai WaNotifier). Dua hal berbeda, jadi tidak
 * boleh disatukan: wali bisa mengizinkan dihubungi tapi belum tentu setuju
 * data biometrik anaknya diproses, dan sebaliknya.
 *
 * Siap dipakai untuk alur pendaftaran wajah mandiri siswa (fase berikutnya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->timestamp('biometric_consent_at')->nullable()->after('consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('biometric_consent_at');
        });
    }
};
