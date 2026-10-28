<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lokasi sekolah untuk geofencing absensi mandiri siswa.
 *
 * `GET` publik supaya app siswa bisa mengambil titik terbaru saat scan, tanpa
 * perlu redeploy. `PUT` hanya untuk admin (lihat middleware EnsureAdmin).
 */
class SchoolSettingController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->settings->location()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meter' => ['required', 'integer', 'between:10,5000'],
            'geofence_enabled' => ['sometimes', 'boolean'],
        ]);

        $location = $this->settings->updateLocation($data, $request->user(), $request);

        return response()->json([
            'message' => 'Lokasi sekolah berhasil disimpan.',
            'data' => $location,
        ]);
    }
}
