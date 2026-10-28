<?php

namespace App\Services;

/**
 * Validasi area sekolah berbasis Haversine.
 *
 * Klien tetap bisa memalsukan koordinat; validasi server di sini menutup
 * bypass naif dan memastikan aturan radius benar-benar berlaku.
 */
class GeoFenceService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{
     *     ok: bool,
     *     code: string|null,
     *     message: string|null,
     *     distance_meter: int|null,
     *     radius_meter: int
     * }
     */
    public function evaluate(?float $latitude, ?float $longitude): array
    {
        $location = $this->settings->location();
        $radius = (int) $location['radius_meter'];

        if (! $location['configured']) {
            return [
                'ok' => false,
                'code' => 'location_not_configured',
                'message' => 'Titik sekolah belum diatur admin. Hubungi petugas.',
                'distance_meter' => null,
                'radius_meter' => $radius,
            ];
        }

        if ($latitude === null || $longitude === null) {
            return [
                'ok' => false,
                'code' => 'location_required',
                'message' => 'Aktifkan GPS dan izinkan akses lokasi untuk melakukan absensi.',
                'distance_meter' => null,
                'radius_meter' => $radius,
            ];
        }

        $distance = (int) round($this->distanceMeters(
            $latitude,
            $longitude,
            (float) $location['latitude'],
            (float) $location['longitude'],
        ));

        if ($distance > $radius) {
            return [
                'ok' => false,
                'code' => 'outside_school_area',
                'message' => sprintf(
                    'Anda berada di luar area sekolah (jarak %d m, maksimal %d m).',
                    $distance,
                    $radius,
                ),
                'distance_meter' => $distance,
                'radius_meter' => $radius,
            ];
        }

        return [
            'ok' => true,
            'code' => null,
            'message' => null,
            'distance_meter' => $distance,
            'radius_meter' => $radius,
        ];
    }

    /**
     * Jarak ke titik sekolah bila koordinat & titik tersedia. Dipakai untuk
     * menyimpan jejak meskipun geofence sedang dimatikan.
     */
    public function distanceFromSchool(?float $latitude, ?float $longitude): ?int
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $location = $this->settings->location();
        if (! $location['configured']) {
            return null;
        }

        return (int) round($this->distanceMeters(
            $latitude,
            $longitude,
            (float) $location['latitude'],
            (float) $location['longitude'],
        ));
    }

    /**
     * Jarak dua koordinat dalam meter (rumus Haversine).
     */
    public function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $toRad = static fn (float $degrees): float => $degrees * M_PI / 180.0;

        $dLat = $toRad($lat2 - $lat1);
        $dLon = $toRad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
