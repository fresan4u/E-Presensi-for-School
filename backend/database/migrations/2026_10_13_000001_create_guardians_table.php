<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship'); // ayah, ibu, wali
            $table->string('phone');        // satu nomor per baris (patuh 1NF)
            $table->boolean('is_primary')->default(false);
            // Waktu wali memberi izin untuk dihubungi. Tanpa ini kontak tidak
            // boleh dipakai (etika/privas).
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
