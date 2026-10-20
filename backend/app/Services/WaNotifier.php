<?php

namespace App\Services;

use App\Jobs\SendWaAttendanceNotification;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\WaOutbox;
use Illuminate\Support\Facades\Storage;

class WaNotifier
{
    /**
     * Status absensi yang diinfokan real-time ke wali. Izin/sakit/alpa hanya
     * dicatat di rekap, jadi tidak perlu mengirim chat.
     */
    private const array NOTIFIABLE_STATUSES = ['hadir', 'terlambat'];

    /**
     * Siapkan baris outbox untuk satu absensi baru, lalu antrekan job kirimnya.
     *
     * Mengembalikan outbox bila dibuat, atau null saat fitur dinonaktifkan
     * (`WA_ENABLED=false`) supaya alur absensi tidak tersentuh sama sekali.
     */
    public function queueFor(Attendance $attendance): ?WaOutbox
    {
        if (! config('wa.enabled')) {
            return null;
        }

        if (! in_array($attendance->status?->code, self::NOTIFIABLE_STATUSES, true)) {
            return $this->skip($attendance, null, 'status_not_notified');
        }

        $guardian = $this->recipientFor($attendance);

        if ($guardian === null) {
            return $this->skip($attendance, null, 'no_consent_or_phone');
        }

        $phone = $this->normalizePhone($guardian->phone);

        if ($phone === null) {
            return $this->skip($attendance, $guardian, 'invalid_phone');
        }

        $outbox = WaOutbox::create([
            'attendance_id' => $attendance->id,
            'guardian_id' => $guardian->id,
            'recipient_phone' => $phone,
            'message' => $this->buildMessage($attendance, $guardian),
            'photo_path' => $attendance->photo_path,
            'status' => WaOutbox::STATUS_PENDING,
        ]);

        SendWaAttendanceNotification::dispatch($outbox->id);

        return $outbox;
    }

    /**
     * Wali penerima: sudah memberi izin dihubungi (`consent_at`) & punya
     * nomor, utamakan wali utama — aturan yang sama dengan endpoint rekap.
     */
    private function recipientFor(Attendance $attendance): ?Guardian
    {
        return $attendance->student
            ->guardians()
            ->whereNotNull('consent_at')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByDesc('is_primary')
            ->first();
    }

    private function skip(Attendance $attendance, ?Guardian $guardian, string $reason): WaOutbox
    {
        return WaOutbox::create([
            'attendance_id' => $attendance->id,
            'guardian_id' => $guardian?->id,
            'message' => '',
            'photo_path' => $attendance->photo_path,
            'status' => WaOutbox::STATUS_SKIPPED,
            'skip_reason' => $reason,
        ]);
    }

    /**
     * Normalisasi nomor wali ke format internasional tanpa `+` (62xxx...).
     * Nomor lokal `08xx`/`8xx` dipetakan ke prefix Indonesia `62`.
     */
    public function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^62\d{9,13}$/', $digits) === 1 ? $digits : null;
    }

    /**
     * Susun teks pesan dari template sesi (`config('wa.templates.<sesi>')`,
     * cadangan `wa.templates.attendance`). Baris yang menjadi kosong setelah
     * substitusi (mis. `:catatan` tanpa catatan) dibuang agar pesan tetap rapi.
     */
    public function buildMessage(Attendance $attendance, Guardian $guardian): string
    {
        $student = $attendance->student;
        $checkedAt = $attendance->checked_at ?? $attendance->created_at ?? now();

        $replacements = [
            ':wali' => $guardian->name,
            ':sesi' => $attendance->session?->code === 'pulang' ? 'pulang' : 'datang',
            ':nama' => $student->full_name,
            ':nis' => (string) ($student->nis ?? $student->nisn),
            ':tanggal' => $checkedAt->copy()->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            ':jam' => $checkedAt->format('H:i'),
            ':kelas' => $this->classroomName($student),
            ':status' => $attendance->status?->label ?? '-',
            ':catatan' => filled($attendance->note) ? 'Catatan: '.$attendance->note : '',
            ':foto' => filled($attendance->photo_path) ? 'Berikut foto bukti absensi.' : '',
        ];

        return collect(preg_split('/\r?\n/', $this->templateFor($attendance->session?->code)))
            ->map(fn (string $line) => trim(strtr($line, $replacements)))
            ->reject(fn (string $line) => $line === '')
            ->implode("\n");
    }

    /**
     * Template pesan untuk kode sesi tertentu, dengan cadangan template umum
     * supaya sesi baru tetap bisa dikirim tanpa perubahan kode.
     */
    private function templateFor(?string $sessionCode): string
    {
        $templates = (array) config('wa.templates');

        return (string) ($templates[$sessionCode] ?? $templates['attendance'] ?? '');
    }

    /** Nama kelas aktif siswa pada tahun ajaran berjalan (bila terdaftar). */
    private function classroomName(Student $student): string
    {
        $yearId = AcademicYear::where('is_active', true)->value('id');

        $enrollment = $student->enrollments()
            ->where('is_active', true)
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->with('classroom')
            ->latest()
            ->first();

        return $enrollment?->classroom?->name ?? '-';
    }

    /** Foto bukti absensi siap dilampirkan (base64 tanpa prefix data URI). */
    public function photoBase64(WaOutbox $outbox): ?string
    {
        if (blank($outbox->photo_path) || ! Storage::exists($outbox->photo_path)) {
            return null;
        }

        return base64_encode(Storage::get($outbox->photo_path));
    }
}
