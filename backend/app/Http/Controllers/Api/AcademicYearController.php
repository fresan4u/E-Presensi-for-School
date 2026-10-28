<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicYearRequest;
use App\Http\Requests\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller
{
    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $data = $request->validated();
        $activate = (bool) ($data['is_active'] ?? false);

        $year = DB::transaction(function () use ($data, $activate): AcademicYear {
            if ($activate) {
                AcademicYear::query()->update(['is_active' => false]);
            }

            return AcademicYear::create([
                'label' => $data['label'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $activate,
            ]);
        });

        // Tahun pertama otomatis aktif supaya sistem tidak berjalan tanpa
        // konteks tahun ajaran.
        if (! AcademicYear::query()->active()->exists()) {
            $year->update(['is_active' => true]);
        }

        return (new AcademicYearResource($year->refresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $request->validated();
        $activate = (bool) ($data['is_active'] ?? $academicYear->is_active);

        if (! $activate && $academicYear->is_active) {
            $anotherActive = AcademicYear::query()
                ->active()
                ->whereKeyNot($academicYear->id)
                ->exists();

            if (! $anotherActive) {
                return response()->json([
                    'message' => 'Minimal satu tahun ajaran harus aktif. Aktifkan tahun lain terlebih dahulu.',
                ], 422);
            }
        }

        DB::transaction(function () use ($academicYear, $data, $activate): void {
            if ($activate) {
                AcademicYear::query()->whereKeyNot($academicYear->id)->update(['is_active' => false]);
            }

            $academicYear->update([
                'label' => $data['label'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $activate,
            ]);
        });

        return (new AcademicYearResource($academicYear->refresh()))->response();
    }

    public function activate(AcademicYear $academicYear): JsonResponse
    {
        DB::transaction(function () use ($academicYear): void {
            AcademicYear::query()->whereKeyNot($academicYear->id)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return (new AcademicYearResource($academicYear->refresh()))
            ->additional(['message' => "Tahun ajaran {$academicYear->label} diaktifkan."])
            ->response();
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->is_active) {
            return response()->json([
                'message' => 'Tahun ajaran aktif tidak boleh dihapus. Aktifkan tahun lain terlebih dahulu.',
            ], 422);
        }

        if ($academicYear->enrollments()->exists() || $academicYear->attendances()->exists()) {
            return response()->json([
                'message' => 'Tahun ajaran sudah dipakai data siswa atau absensi sehingga tidak bisa dihapus.',
            ], 422);
        }

        $academicYear->delete();

        return response()->json(['message' => 'Tahun ajaran berhasil dihapus.']);
    }
}
