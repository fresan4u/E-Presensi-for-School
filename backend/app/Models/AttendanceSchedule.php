<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'day_of_week', 'is_active', 'masuk_start', 'masuk_end',
    'handover_at', 'pulang_start', 'pulang_end', 'late_after',
])]
class AttendanceSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
