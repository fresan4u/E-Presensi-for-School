<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_class_enrollments', function (Blueprint $table) {
            $table->index(
                ['academic_year_id', 'classroom_id', 'is_active'],
                'sce_year_classroom_active_index',
            );
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index(['status_id', 'full_name'], 'students_status_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('student_class_enrollments', function (Blueprint $table) {
            $table->dropIndex('sce_year_classroom_active_index');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_status_name_index');
        });
    }
};
