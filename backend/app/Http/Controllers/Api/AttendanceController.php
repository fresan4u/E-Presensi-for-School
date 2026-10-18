<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanAttendanceRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceMethod;
use App\Models\AttendanceSession;
use App\Models\AttendanceStatus;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentFaceDescriptor;
use App\Services\FaceMatchingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private FaceMatchingService $faceMatcher) {}

    /** Relasi standar untuk resource absensi. */
    private function relations(): array
    {
        return [
            'status', 'method', 'session',
            'student.status',
            'student.enrollments.classroom.gradeLevel',
            'student.enrollments.classroom.major',
        ];
    }

    public function index(Request $request)
    {
        $query = Attendance::query()->with($this->relations());

        if ($date = $request->query('date')) {
            $query->whereDate('attendance_date', $date);
        }

        if ($studentId = (int) $request->query('student_id', 0)) {
            $query->where('student_id', $studentId);
        }

        if ($classroomId = (int) $request->query('classroom_id', 0)) {
            $query->whereHas('student.enrollments', fn ($q) => $q->where('is_active', true)->where('classroom_id', $classroomId));
        }

        if ($sessionCode = $request->query('session')) {
            $query->whereHas('session', fn ($q) => $q->where('code', $sessionCode));
        }

        if ($methodCode = $request->query('method')) {
            $query->whereHas('method', fn ($q) => $q->where('code', $methodCode));
        }

        if ($statusCode = $request->query('status')) {
            $query->whereHas('status', fn ($q) => $q->where('code', $statusCode));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 25) ?: 25;
        $perPage = max(1, min($perPage, 200));

        return AttendanceResource::collection(
            $query->orderByDesc('checked_at')->paginate($perPage)->withQueryString(),
        );
    }

    /**
     * Rekap kehadiran harian satu kelas.
     *
     * Mengembalikan SELURUH roster siswa aktif di kelas tersebut (bukan hanya
     * yang sudah absen), sehingga siswa tanpa baris absensi berstatus `alpa`.
     * Kontak wali hanya disertakan bila wali sudah memberi izin (`consent_at`).
     */
    public function recap(Request $request): JsonResponse
    {
        $classroomId = (int) $request->query('classroom_id', 0);
        if ($classroomId <= 0) {
            return response()->json(['message' => 'Parameter classroom_id wajib diisi.'], 422);
        }

        $date = (string) ($request->query('date') ?: Carbon::now()->toDateString());

        $yearId = (int) $request->query('academic_year_id', 0)
            ?: AcademicYear::where('is_active', true)->value('id');

        $classroom = Classroom::with(['gradeLevel', 'major'])->find($classroomId);
        if (! $classroom) {
            return response()->json(['message' => 'Kelas tidak ditemukan.'], 404);
        }

        $students = Student::query()
            ->whereHas('enrollments', function ($query) use ($classroomId, $yearId) {
                $query->where('is_active', true)
                    ->where('classroom_id', $classroomId)
                    ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId));
            })
            ->with(['guardians'])
            ->orderBy('full_name')
            ->get();

        $sessionMasukId = AttendanceSession::where('code', 'masuk')->value('id');

        $attendances = Attendance::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereDate('attendance_date', $date)
            ->when($sessionMasukId, fn ($query) => $query->where('session_id', $sessionMasukId))
            ->with(['status', 'method', 'session'])
            ->get()
            ->keyBy('student_id');

        $alpa = AttendanceStatus::where('code', 'alpa')->first();
        $summary = ['total' => $students->count(), 'hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        $rows = $students->map(function (Student $student) use ($attendances, $alpa, &$summary) {
            $attendance = $attendances->get($student->id);

            $statusCode = $attendance?->status?->code ?? 'alpa';
            $statusLabel = $attendance?->status?->label ?? $alpa?->label ?? 'Alpa';

            if (array_key_exists($statusCode, $summary)) {
                $summary[$statusCode]++;
            }

            // Wali utama yang sudah memberi izin (praktik privasi: consent).
            $guardian = $student->guardians
                ->filter(fn ($g) => $g->consent_at !== null && ! empty($g->phone))
                ->sortByDesc('is_primary')
                ->first();

            return [
                'id' => $student->id,
                'nis' => $student->nis,
                'full_name' => $student->full_name,
                'status' => ['code' => $statusCode, 'label' => $statusLabel],
                'checked_at' => $attendance?->checked_at,
                'note' => $attendance?->note,
                'guardian' => $guardian ? [
                    'name' => $guardian->name,
                    'relationship' => $guardian->relationship,
                    'phone' => $guardian->phone,
                ] : null,
            ];
        });

        return response()->json([
            'data' => [
                'date' => $date,
                'academic_year_id' => $yearId,
                'classroom' => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'grade_level' => $classroom->gradeLevel ? [
                        'id' => $classroom->gradeLevel->id,
                        'name' => $classroom->gradeLevel->name,
                        'level' => $classroom->gradeLevel->level,
                    ] : null,
                    'major' => $classroom->major ? [
                        'id' => $classroom->major->id,
                        'code' => $classroom->major->code,
                        'name' => $classroom->major->name,
                    ] : null,
                ],
                'summary' => $summary,
                'students' => $rows->values(),
            ],
        ]);
    }

    public function scan(ScanAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $at = Carbon::now();

        $session = AttendanceSession::where('code', $data['session'])->firstOrFail();

        $match = $this->faceMatcher->findMatch($data['descriptor']);

        if ($match === null) {
            return response()->json([
                'message' => 'Wajah tidak dikenali.',
                'code' => 'face_not_recognized',
            ], 404);
        }

        $student = $match['descriptor']->student;
        $attendance = $this->persistAttendance($student, $session, $at, $match['descriptor'], $match['distance']);

        $created = $attendance->wasRecentlyCreated;

        return (new AttendanceResource($attendance->load($this->relations())))
            ->additional(['meta' => [
                'created' => $created,
                'distance' => round($match['distance'], 4),
                'threshold' => $this->faceMatcher->threshold(),
            ]])
            ->response();
    }

    /**
     * Absensi mandiri siswa (app mobile).
     *
     * Descriptor wajah diverifikasi 1:1 hanya terhadap wajah terdaftar milik
     * siswa yang sedang login (token Sanctum), sehingga absensi selalu tercatat
     * atas nama pemilik akun — tidak bisa titip absen.
     */
    public function scanSelf(ScanAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $at = Carbon::now();

        /** @var Student $student */
        $student = $request->user();
        $session = AttendanceSession::where('code', $data['session'])->firstOrFail();

        $match = $this->faceMatcher->verifyForStudent($student, $data['descriptor']);

        if ($match === null) {
            return response()->json([
                'message' => 'Wajah tidak cocok dengan data siswa yang login.',
                'code' => 'face_not_recognized',
            ], 404);
        }

        $attendance = $this->persistAttendance($student, $session, $at, $match['descriptor'], $match['distance']);

        return (new AttendanceResource($attendance->load($this->relations())))
            ->additional(['meta' => [
                'created' => $attendance->wasRecentlyCreated,
                'distance' => round($match['distance'], 4),
                'threshold' => $this->faceMatcher->threshold(),
            ]])
            ->response();
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $session = AttendanceSession::where('code', $data['session'])->firstOrFail();
        $status = AttendanceStatus::where('code', $data['status'])->firstOrFail();
        $methodCode = $data['method'] ?? 'manual';
        $methodId = AttendanceMethod::where('code', $methodCode)->value('id');
        $yearId = AcademicYear::where('is_active', true)->value('id');

        $date = isset($data['attendance_date'])
            ? Carbon::parse($data['attendance_date'])
            : Carbon::now();
        $checkedAt = isset($data['checked_at']) ? Carbon::parse($data['checked_at']) : Carbon::now();

        $attendance = Attendance::updateOrCreate(
            [
                'student_id' => $data['student_id'],
                'attendance_date' => $date->toDateString(),
                'session_id' => $session->id,
            ],
            [
                'academic_year_id' => $yearId,
                'status_id' => $status->id,
                'method_id' => $methodId,
                'checked_at' => $checkedAt,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()?->id,
            ],
        );

        return (new AttendanceResource($attendance->load($this->relations())))
            ->response()
            ->setStatusCode($attendance->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): AttendanceResource
    {
        $data = $request->validated();

        $update = [];
        if (array_key_exists('status', $data)) {
            $update['status_id'] = AttendanceStatus::where('code', $data['status'])->value('id');
        }
        if (array_key_exists('note', $data)) {
            $update['note'] = $data['note'];
        }

        $attendance->update($update);

        return new AttendanceResource($attendance->load($this->relations()));
    }

    /**
     * Simpan absensi (idempoten per siswa + tanggal + sesi) memakai metode
     * face recognition. Dipakai kiosk petugas (`scan`) maupun app mobile
     * (`scanSelf`).
     */
    private function persistAttendance(
        Student $student,
        AttendanceSession $session,
        Carbon $at,
        ?StudentFaceDescriptor $descriptor = null,
        ?float $distance = null,
    ): Attendance {
        $status = $this->resolveStatus($session, $at);
        $yearId = AcademicYear::where('is_active', true)->value('id');
        $methodId = AttendanceMethod::where('code', 'face_recognition')->value('id');

        return Attendance::firstOrCreate(
            [
                'student_id' => $student->id,
                'attendance_date' => $at->toDateString(),
                'session_id' => $session->id,
            ],
            [
                'academic_year_id' => $yearId,
                'status_id' => $status->id,
                'method_id' => $methodId,
                'matched_descriptor_id' => $descriptor?->id,
                'match_distance' => $distance,
                'checked_at' => $at,
            ],
        );
    }

    private function resolveStatus(AttendanceSession $session, Carbon $at): AttendanceStatus
    {
        if ($session->code === 'masuk' && $session->boundary_time) {
            $boundary = Carbon::parse($at->toDateString().' '.$session->boundary_time);
            $code = $at->greaterThan($boundary) ? 'terlambat' : 'hadir';

            return AttendanceStatus::where('code', $code)->firstOrFail();
        }

        return AttendanceStatus::where('code', 'hadir')->firstOrFail();
    }
}
