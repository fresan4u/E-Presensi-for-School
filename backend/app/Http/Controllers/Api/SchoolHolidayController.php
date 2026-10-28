<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchoolHolidayRequest;
use App\Http\Requests\UpdateSchoolHolidayRequest;
use App\Http\Resources\SchoolHolidayResource;
use App\Models\SchoolHoliday;
use App\Models\SettingsAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolHolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year') ?: now()->year);

        $holidays = SchoolHoliday::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => SchoolHolidayResource::collection($holidays),
        ]);
    }

    public function store(StoreSchoolHolidayRequest $request): JsonResponse
    {
        $holiday = SchoolHoliday::query()->create($request->validated());

        SettingsAuditLog::record(
            $request->user(),
            'create',
            'school_holidays',
            $holiday->id,
            ['new' => $holiday->toArray()],
            $request,
        );

        return response()->json(
            ['data' => new SchoolHolidayResource($holiday)],
            201,
        );
    }

    public function update(UpdateSchoolHolidayRequest $request, SchoolHoliday $schoolHoliday): JsonResponse
    {
        $old = $schoolHoliday->toArray();
        $schoolHoliday->update($request->validated());

        SettingsAuditLog::record(
            $request->user(),
            'update',
            'school_holidays',
            $schoolHoliday->id,
            ['old' => $old, 'new' => $schoolHoliday->fresh()->toArray()],
            $request,
        );

        return response()->json([
            'data' => new SchoolHolidayResource($schoolHoliday->fresh()),
        ]);
    }

    public function destroy(Request $request, SchoolHoliday $schoolHoliday): JsonResponse
    {
        SettingsAuditLog::record(
            $request->user(),
            'delete',
            'school_holidays',
            $schoolHoliday->id,
            ['old' => $schoolHoliday->toArray()],
            $request,
        );

        $schoolHoliday->delete();

        return response()->json(['message' => 'Hari libur dihapus.']);
    }
}
