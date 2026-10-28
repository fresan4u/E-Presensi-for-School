<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAttendanceSchedulesRequest;
use App\Http\Resources\AttendanceScheduleResource;
use App\Models\AttendanceSchedule;
use App\Models\SettingsAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        $schedules = AttendanceSchedule::query()
            ->orderBy('day_of_week')
            ->get();

        return response()->json([
            'data' => AttendanceScheduleResource::collection($schedules),
        ]);
    }

    public function update(UpdateAttendanceSchedulesRequest $request): JsonResponse
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->validated()['schedules'];
        $old = AttendanceSchedule::query()->orderBy('day_of_week')->get()->toArray();

        DB::transaction(function () use ($rows, $old, $request): void {
            foreach ($rows as $row) {
                AttendanceSchedule::query()->updateOrCreate(
                    ['day_of_week' => (int) $row['day_of_week']],
                    [
                        'is_active' => (bool) $row['is_active'],
                        'masuk_start' => $this->time($row['masuk_start']),
                        'masuk_end' => $this->time($row['masuk_end']),
                        'handover_at' => $this->time($row['handover_at'] ?? null),
                        'pulang_start' => $this->time($row['pulang_start']),
                        'pulang_end' => $this->time($row['pulang_end']),
                        'late_after' => $this->time($row['late_after'] ?? null),
                    ],
                );
            }

            SettingsAuditLog::record(
                $request->user(),
                'update',
                'attendance_schedules',
                null,
                ['old' => $old, 'new' => $rows],
                $request,
            );
        });

        return $this->index();
    }

    private function time(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
