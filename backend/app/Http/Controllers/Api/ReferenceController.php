<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\AcademicYear;
use App\Models\AttendanceMethod;
use App\Models\AttendanceSession;
use App\Models\AttendanceStatus;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\StudentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReferenceController extends Controller
{
    public function classrooms(): AnonymousResourceCollection
    {
        $classrooms = Classroom::with(['gradeLevel', 'major'])
            ->orderBy('grade_level_id')
            ->orderBy('major_id')
            ->orderBy('rombel')
            ->get();

        return ClassroomResource::collection($classrooms);
    }

    public function majors(): JsonResponse
    {
        return response()->json([
            'data' => Major::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function academicYears(): JsonResponse
    {
        return response()->json([
            'data' => AcademicYear::orderByDesc('label')->get(['id', 'label', 'is_active']),
        ]);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'data' => [
                'student_statuses' => StudentStatus::orderBy('id')->get(['id', 'code', 'label']),
                'attendance_statuses' => AttendanceStatus::orderBy('id')->get(['id', 'code', 'label']),
                'attendance_methods' => AttendanceMethod::orderBy('id')->get(['id', 'code', 'label']),
                'attendance_sessions' => AttendanceSession::orderBy('id')->get(['id', 'code', 'label', 'boundary_time']),
                'attendance_window' => [
                    'self_cutoff' => config('presensi.attendance.self_cutoff'),
                    'handover_at' => config('presensi.attendance.handover_at'),
                    'pulang_from' => config('presensi.attendance.pulang_from'),
                ],
                'face' => [
                    'samples_per_student' => (int) config('face.samples_per_student', 3),
                    'descriptor_length' => (int) config('face.descriptor_length', 128),
                    'match_threshold' => (float) config('face.match_threshold', 0.6),
                    'model_version' => config('face.model_version'),
                ],
            ],
        ]);
    }
}
