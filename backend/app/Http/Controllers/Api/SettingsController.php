<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingsAuditLogResource;
use App\Models\SettingsAuditLog;
use App\Services\AttendanceWindowService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

/**
 * Pengaturan operasional yang boleh diubah admin tanpa deploy.
 *
 * Hanya role admin yang bisa mencapai controller ini (lihat middleware
 * `EnsureAdmin` pada routes/api.php).
 */
class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private AttendanceWindowService $attendanceWindow,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'attendance' => [
                    'self_scan_enabled' => $this->settings->selfScanEnabled(),
                ],
                'face' => [
                    'match_threshold' => $this->settings->faceThreshold(),
                    'samples_per_student' => $this->settings->faceSamples(),
                ],
                'notification' => [
                    'wa_enabled' => $this->settings->waEnabled(),
                ],
                'location' => $this->settings->location(),
            ],
            'status' => array_merge($this->attendanceWindow->today(), [
                'server_time' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->update(
            Arr::dot($request->validated()),
            $request->user(),
            $request,
        );

        return $this->show();
    }

    public function audit(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 25) ?: 25;
        $perPage = max(1, min($perPage, 100));

        $logs = SettingsAuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return SettingsAuditLogResource::collection($logs);
    }
}
