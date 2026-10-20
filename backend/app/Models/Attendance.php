<?php

namespace App\Models;

use App\Observers\AttendanceObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'student_id', 'academic_year_id', 'attendance_date', 'session_id',
    'status_id', 'method_id', 'matched_descriptor_id', 'match_distance',
    'checked_at', 'note', 'photo_path', 'created_by',
])]
#[ObservedBy(AttendanceObserver::class)]
class Attendance extends Model
{
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'checked_at' => 'datetime',
            'match_distance' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class, 'status_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(AttendanceMethod::class, 'method_id');
    }

    public function matchedDescriptor(): BelongsTo
    {
        return $this->belongsTo(StudentFaceDescriptor::class, 'matched_descriptor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Satu baris outbox notifikasi WhatsApp untuk absensi ini (bila dibuat). */
    public function waOutbox(): HasOne
    {
        return $this->hasOne(WaOutbox::class);
    }
}
