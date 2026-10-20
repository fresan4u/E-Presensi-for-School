<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Bukti foto hasil capture frame kamera saat scan (nullable:
            // absensi manual & scan tanpa foto tidak menyimpan gambar).
            $table->string('photo_path')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
