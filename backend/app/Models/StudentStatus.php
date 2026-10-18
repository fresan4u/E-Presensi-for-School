<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label'])]
class StudentStatus extends Model
{
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'status_id');
    }
}
