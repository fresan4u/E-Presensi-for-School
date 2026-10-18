<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sederhanakan data siswa: cukup NIS, nama lengkap, dan kelas.
     * `nis` jadi identitas tunggal; `nisn`, `gender`, dan `birth_date` dihapus.
     */
    public function up(): void
    {
        // Baris lama yang belum punya NIS: isi dari NISN agar tidak null.
        DB::table('students')
            ->where(fn ($query) => $query->whereNull('nis')->orWhere('nis', ''))
            ->update(['nis' => DB::raw('nisn')]);

        Schema::table('students', function (Blueprint $table) {
            $table->string('nis')->nullable(false)->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['nisn', 'gender', 'birth_date']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('nisn')->nullable()->unique()->after('id');
            $table->enum('gender', ['L', 'P'])->nullable()->after('full_name');
            $table->date('birth_date')->nullable()->after('gender');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('nis')->nullable()->change();
        });
    }
};
