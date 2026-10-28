<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentFaceDescriptor;

/**
 * Pencocokan wajah berbasis jarak Euclidean antar descriptor (128 angka).
 */
class FaceMatchingService
{
    public function __construct(
        private SettingsService $settings,
        private ?float $threshold = null,
    ) {}

    public function threshold(): float
    {
        return $this->threshold ?? $this->settings->faceThreshold();
    }

    /**
     * Cari descriptor terdekat dengan input (hanya siswa aktif).
     *
     * @param  array<int, float|int>  $descriptor
     * @return array{descriptor: StudentFaceDescriptor, distance: float}|null
     */
    public function findMatch(array $descriptor): ?array
    {
        $best = null;

        StudentFaceDescriptor::query()
            ->where('is_active', true)
            ->whereHas('student.status', fn ($q) => $q->where('code', 'aktif'))
            ->with('student')
            ->chunkById(500, function ($candidates) use ($descriptor, &$best) {
                foreach ($candidates as $candidate) {
                    $distance = self::euclidean($descriptor, $candidate->descriptor ?? []);
                    if ($distance === null) {
                        continue;
                    }
                    if ($best === null || $distance < $best['distance']) {
                        $best = ['descriptor' => $candidate, 'distance' => $distance];
                    }
                }
            });

        if ($best === null || $best['distance'] > $this->threshold()) {
            return null;
        }

        return $best;
    }

    /**
     * Verifikasi 1:1 — descriptor dicocokkan HANYA dengan wajah terdaftar milik
     * `$student`. Dipakai absensi mandiri di app mobile agar siswa tidak bisa
     * titip absen lewat wajah orang lain.
     *
     * @param  array<int, float|int>  $descriptor
     * @return array{descriptor: StudentFaceDescriptor, distance: float}|null
     */
    public function verifyForStudent(Student $student, array $descriptor): ?array
    {
        $best = null;

        $student->faceDescriptors()
            ->where('is_active', true)
            ->get()
            ->each(function (StudentFaceDescriptor $candidate) use ($descriptor, &$best) {
                $distance = self::euclidean($descriptor, $candidate->descriptor ?? []);
                if ($distance === null) {
                    return;
                }
                if ($best === null || $distance < $best['distance']) {
                    $best = ['descriptor' => $candidate, 'distance' => $distance];
                }
            });

        if ($best === null || $best['distance'] > $this->threshold()) {
            return null;
        }

        return $best;
    }

    /**
     * Jarak Euclidean antara dua vektor. Null bila dimensi tidak cocok.
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public static function euclidean(array $a, array $b): ?float
    {
        $n = count($a);
        if ($n === 0 || $n !== count($b)) {
            return null;
        }

        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $diff = (float) $a[$i] - (float) $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
