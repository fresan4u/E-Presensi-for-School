<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_face_descriptors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedTinyInteger('sample_no');       // 1..3
            $table->json('descriptor');                     // 128 angka
            $table->string('model_version')->nullable();    // face-api.js 0.22.2
            $table->float('quality_score')->nullable();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['student_id', 'sample_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_face_descriptors');
    }
};
