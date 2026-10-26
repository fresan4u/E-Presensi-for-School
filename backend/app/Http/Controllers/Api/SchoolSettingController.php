<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lokasi sekolah untuk geofencing absensi mandiri siswa.
 *
 * `GET` publik supaya app siswa bisa mengambil titik terbaru saat scan, tanpa
 * perlu redeploy. `PUT` hanya untuk admin/petugas yang login.
 */
class SchoolSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload(SchoolSetting::current())]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meter' => ['required', 'integer', 'between:10,5000'],
        ]);

        $setting = SchoolSetting::query()->first() ?? new SchoolSetting;
        $setting->fill($data)->save();

        return response()->json([
            'message' => 'Lokasi sekolah berhasil disimpan.',
            'data' => $this->payload($setting),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SchoolSetting $setting): array
    {
        return [
            'latitude' => (float) $setting->latitude,
            'longitude' => (float) $setting->longitude,
            'radius_meter' => (int) $setting->radius_meter,
            'updated_at' => $setting->updated_at,
        ];
    }
}
