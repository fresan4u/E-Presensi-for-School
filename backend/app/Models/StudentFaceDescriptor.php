<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'sample_no', 'descriptor', 'model_version', 'quality_score', 'captured_by', 'is_active'])]
class StudentFaceDescriptor extends Model
{
    protected function casts(): array
    {
        return [
            'descriptor' => 'array',
            'sample_no' => 'integer',
            'quality_score' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
