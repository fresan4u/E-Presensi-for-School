<?php

namespace App\Services;

use App\Models\AttendanceSchedule;
use App\Models\SchoolHoliday;
use Carbon\Carbon;

/**
 * Menentukan apakah absensi mandiri sedang buka dan sesi apa yang berlaku.
 *
 * Sumber kebenaran: jadwal per hari (`attendance_schedules`), kalender libur
 * (`school_holidays`), dan saklar global `attendance.self_scan_enabled`.
 * Bila tabel jadwal masih kosong (deployment lama), dipakai fallback dari
 * `config('presensi.attendance')` supaya perilaku lama tidak berubah.
 */
class AttendanceWindowService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{
     *     is_open: bool,
     *     session: string|null,
     *     reason: string|null,
     *     message: string|null,
     *     schedule: array<string, mixed>|null,
     *     holiday: SchoolHoliday|null
     * }
     */
    public function for(Carbon $at): array
    {
        $holiday = SchoolHoliday::query()
            ->whereDate('date', $at->toDateString())
            ->first();

        if ($holiday !== null) {
            return $this->closed(
                'holiday',
                sprintf('Hari ini libur: %s. Absensi mandiri ditutup.', $holiday->name),
                $holiday,
            );
        }

        if (! $this->settings->selfScanEnabled()) {
            return $this->closed('disabled', 'Absensi mandiri sedang dinonaktifkan oleh admin.');
        }

        $schedule = $this->scheduleFor($at);

        if ($schedule === null || ! $schedule['is_active']) {
            return $this->closed(
                'not_school_day',
                'Tidak ada jadwal absensi mandiri untuk hari ini.',
                null,
                $schedule,
            );
        }

        $time = $at->format('H:i:s');

        if ($this->within($time, $schedule['masuk_start'], $schedule['masuk_end'])) {
            return $this->open('masuk', $schedule, $holiday);
        }

        if ($this->within($time, $schedule['pulang_start'], $schedule['pulang_end'])) {
            return $this->open('pulang', $schedule, $holiday);
        }

        return $this->closed(
            'outside_window',
            sprintf(
                'Absen mandiri ditutup. Absen masuk %s–%s; absen pulang %s–%s.',
                $this->short($schedule['masuk_start']),
                $this->short($schedule['masuk_end']),
                $this->short($schedule['pulang_start']),
                $this->short($schedule['pulang_end']),
            ),
            null,
            $schedule,
        );
    }

    /**
     * Ringkasan jendela hari ini untuk endpoint meta & panel admin.
     *
     * @return array<string, mixed>
     */
    public function today(?Carbon $at = null): array
    {
        $at ??= Carbon::now();
        $window = $this->for($at);
        $schedule = $window['schedule'];
        $holiday = $window['holiday'];

        return [
            'date' => $at->toDateString(),
            'day_of_week' => $at->dayOfWeekIso,
            'day_name' => $this->dayName($at->dayOfWeekIso),

            // Key lama — dipertahankan agar client yang sudah terdeploy aman.
            'self_cutoff' => $schedule
                ? $this->short($schedule['masuk_end'])
                : config('presensi.attendance.self_cutoff', '07:00'),
            'handover_at' => $schedule
                ? $this->short($schedule['handover_at'])
                : config('presensi.attendance.handover_at', '06:40'),
            'pulang_from' => $schedule
                ? $this->short($schedule['pulang_start'])
                : config('presensi.attendance.pulang_from', '15:00'),

            // Key baru untuk jadwal per hari.
            'masuk_start' => $schedule ? $this->short($schedule['masuk_start']) : null,
            'masuk_end' => $schedule ? $this->short($schedule['masuk_end']) : null,
            'pulang_end' => $schedule ? $this->short($schedule['pulang_end']) : null,
            'late_after' => $schedule ? $this->short($schedule['late_after']) : null,

            'self_scan_enabled' => $this->settings->selfScanEnabled(),
            'is_school_day' => (bool) ($schedule['is_active'] ?? false) && $holiday === null,
            'is_holiday' => $holiday !== null,
            'holiday_name' => $holiday?->name,
            'is_open' => $window['is_open'],
            'current_session' => $window['session'],
            'reason' => $window['reason'],
            'message' => $window['message'],
        ];
    }

    /**
     * Jadwal efektif untuk tanggal tertentu.
     *
     * @return array<string, mixed>|null
     */
    public function scheduleFor(Carbon $at): ?array
    {
        $schedule = AttendanceSchedule::query()
            ->where('day_of_week', $at->dayOfWeekIso)
            ->first();

        if ($schedule !== null) {
            return [
                'day_of_week' => $schedule->day_of_week,
                'is_active' => (bool) $schedule->is_active,
                'masuk_start' => $schedule->masuk_start,
                'masuk_end' => $schedule->masuk_end,
                'handover_at' => $schedule->handover_at,
                'pulang_start' => $schedule->pulang_start,
                'pulang_end' => $schedule->pulang_end,
                'late_after' => $schedule->late_after,
            ];
        }

        // Belum ada baris jadwal sama sekali (mis. migrasi belum di-seed):
        // pakai perilaku lama dari config.
        if (AttendanceSchedule::query()->exists()) {
            return null;
        }

        return [
            'day_of_week' => $at->dayOfWeekIso,
            'is_active' => true,
            'masuk_start' => '00:00:00',
            'masuk_end' => $this->full(config('presensi.attendance.self_cutoff', '07:00')),
            'handover_at' => $this->full(config('presensi.attendance.handover_at', '06:40')),
            'pulang_start' => $this->full(config('presensi.attendance.pulang_from', '15:00')),
            'pulang_end' => '23:59:59',
            'late_after' => $this->full(config('presensi.attendance.self_cutoff', '07:00')),
        ];
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    private function open(string $session, array $schedule, ?SchoolHoliday $holiday): array
    {
        return [
            'is_open' => true,
            'session' => $session,
            'reason' => null,
            'message' => null,
            'schedule' => $schedule,
            'holiday' => $holiday,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $schedule
     * @return array<string, mixed>
     */
    private function closed(
        string $reason,
        string $message,
        ?SchoolHoliday $holiday = null,
        ?array $schedule = null,
    ): array {
        return [
            'is_open' => false,
            'session' => null,
            'reason' => $reason,
            'message' => $message,
            'schedule' => $schedule,
            'holiday' => $holiday,
        ];
    }

    private function within(string $time, string $start, string $end): bool
    {
        return $time >= $start && $time <= $end;
    }

    private function short(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    private function full(?string $time): string
    {
        if ($time === null) {
            return '00:00:00';
        }

        return strlen($time) === 5 ? $time.':00' : $time;
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
