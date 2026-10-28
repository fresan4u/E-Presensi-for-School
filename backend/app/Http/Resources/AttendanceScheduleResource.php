<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => (int) $this->day_of_week,
            'day_name' => $this->dayName((int) $this->day_of_week),
            'is_active' => (bool) $this->is_active,
            'masuk_start' => $this->time($this->masuk_start),
            'masuk_end' => $this->time($this->masuk_end),
            'handover_at' => $this->time($this->handover_at),
            'pulang_start' => $this->time($this->pulang_start),
            'pulang_end' => $this->time($this->pulang_end),
            'late_after' => $this->time($this->late_after),
        ];
    }

    private function time(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }

    private function dayName(int $day): string
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ][$day] ?? '-';
    }
}
