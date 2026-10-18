<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['grade_level_id', 'major_id', 'rombel'])]
class Classroom extends Model
{
    protected $appends = ['name'];

    protected function casts(): array
    {
        return ['rombel' => 'integer'];
    }

    /** Nama kelas hasil komposisi, mis. "X RPL 1". */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim(sprintf(
            '%s %s %d',
            $this->gradeLevel?->name ?? '',
            $this->major?->code ?? '',
            $this->rombel,
        )));
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentClassEnrollment::class);
    }
}
