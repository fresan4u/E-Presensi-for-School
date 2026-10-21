<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nis', 'full_name', 'pin', 'status_id', 'must_change_pin', 'pin_changed_at'])]
#[Hidden(['pin'])]
class Student extends Authenticatable
{
    /** @use HasFactory<StudentFactory> */
    use HasApiTokens, HasFactory;

    /**
     * PIN adalah kredensial rahasia (login mobile): hash saat disimpan dan
     * jangan pernah diserialisasi.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'must_change_pin' => 'boolean',
            'pin_changed_at' => 'datetime',
        ];
    }

    /**
     * PIN awal default = NIS (wajib diganti saat login pertama). PIN tetap
     * berlaku bila diisi eksplisit (mis. dari seeder/tes/impor).
     */
    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            if (blank($student->pin)) {
                $student->pin = (string) $student->nis;
                $student->must_change_pin = true;
            }
        });
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(StudentStatus::class, 'status_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentClassEnrollment::class);
    }

    public function faceDescriptors(): HasMany
    {
        return $this->hasMany(StudentFaceDescriptor::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
