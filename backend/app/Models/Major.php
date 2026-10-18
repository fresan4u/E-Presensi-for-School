<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Major extends Model
{
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }
}
