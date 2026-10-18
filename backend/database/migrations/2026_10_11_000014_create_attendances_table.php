<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->date('attendance_date');
            $table->foreignId('session_id')->constrained('attendance_sessions')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('attendance_statuses')->restrictOnDelete();
            $table->foreignId('method_id')->constrained('attendance_methods')->restrictOnDelete();
            $table->foreignId('matched_descriptor_id')->nullable()->constrained('student_face_descriptors')->nullOnDelete();
            $table->decimal('match_distance', 6, 4)->nullable();
            $table->timestamp('checked_at');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'attendance_date', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
