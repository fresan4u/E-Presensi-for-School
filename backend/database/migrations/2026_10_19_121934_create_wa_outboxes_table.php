<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_outboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            // nullable: siswa tanpa wali ber-consent tetap dicatat (status skipped).
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->string('recipient_phone')->nullable();
            $table->text('message');
            $table->string('photo_path')->nullable();
            // pending, sending, sent, failed, skipped.
            $table->string('status')->default('pending');
            $table->string('skip_reason')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->string('wamid')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique('attendance_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_outboxes');
    }
};
