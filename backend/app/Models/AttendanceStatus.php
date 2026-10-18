<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label'])]
class AttendanceStatus extends Model
{
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'status_id');
    }
}
