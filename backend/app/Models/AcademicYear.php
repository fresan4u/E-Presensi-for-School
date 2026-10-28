<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'start_date', 'end_date', 'is_active'])]
class AcademicYear extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** Tahun ajaran yang sedang berjalan. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** ID tahun ajaran aktif (null bila admin belum mengaktifkan satu pun). */
    public static function activeId(): ?int
    {
        return static::query()->active()->value('id');
    }

    /**
     * Tahun ajaran yang mencakup tanggal tertentu. Dipakai saat mencatat
     * absensi agar input mundur tidak selalu masuk ke tahun yang aktif sekarang.
     */
    public static function forDate(Carbon|string $date): ?self
    {
        $date = Carbon::parse($date)->toDateString();

        return static::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('start_date')
            ->first();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentClassEnrollment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
