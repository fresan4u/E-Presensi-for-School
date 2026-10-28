<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('distance_meter')->nullable()->after('longitude');
            $table->decimal('gps_accuracy_meter', 8, 2)->nullable()->after('distance_meter');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['distance_meter', 'gps_accuracy_meter']);
        });
    }
};
