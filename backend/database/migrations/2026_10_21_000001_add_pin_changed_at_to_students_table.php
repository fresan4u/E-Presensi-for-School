<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `pin_changed_at` menandai PIN sudah pernah diganti sekali.
 *
 * PIN hanya boleh diganti SEKALI (saat login pertama, ketika masih PIN bawaan).
 * Kolom ini jadi bukti permanen pergantian itu: selama masih null, endpoint
 * `PUT /student/pin` masih terbuka; setelah terisi, endpoint menolak (403).
 *
 * Sengaja timestamp (bukan boolean) supaya ikut terekam kapan pergantiannya
 * terjadi — berguna untuk audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('pin_changed_at')->nullable()->after('must_change_pin');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('pin_changed_at');
        });
    }
};
