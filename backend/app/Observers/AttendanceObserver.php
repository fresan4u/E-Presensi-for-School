<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Services\WaNotifier;
use Throwable;

class AttendanceObserver
{
    public function __construct(private WaNotifier $notifier) {}

    /**
     * Setiap absensi BARU (scan wajah kiosk/mobile maupun catatan manual)
     * memicu satu notifikasi WhatsApp ke wali. Perubahan atas absensi lama
     * tidak dikirim ulang — cukup menangani event `created`.
     *
     * Kegagalan di dalam pipeline notifikasi dilaporkan (report) tetapi tidak
     * pernah melempar keluar: absensi harus tetap tercatat walau fitur WA
     * bermasalah.
     */
    public function created(Attendance $attendance): void
    {
        try {
            $this->notifier->queueFor($attendance);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
