<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('major_id')->constrained('majors')->restrictOnDelete();
            $table->unsignedTinyInteger('rombel'); // nomor rombel/kelas paralel
            $table->timestamps();

            $table->unique(['grade_level_id', 'major_id', 'rombel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
