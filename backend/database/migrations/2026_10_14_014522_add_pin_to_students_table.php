<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kredensial PIN siswa (login aplikasi mobile).
     *
     * PIN disimpan sebagai hash. Siswa yang sudah ada di-backfill dengan PIN
     * awal = NIS dan ditandai `must_change_pin` supaya wajib menggantinya saat
     * login pertama.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('pin')->nullable()->after('full_name');
            $table->boolean('must_change_pin')->default(false)->after('pin');
        });

        foreach (DB::table('students')->select('id', 'nis')->get() as $student) {
            DB::table('students')->where('id', $student->id)->update([
                'pin' => Hash::make((string) $student->nis),
                'must_change_pin' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['pin', 'must_change_pin']);
        });
    }
};
