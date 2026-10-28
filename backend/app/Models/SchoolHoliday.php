<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name', 'description'])]
class SchoolHoliday extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
