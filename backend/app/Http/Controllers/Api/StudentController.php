<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportStudentsRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassEnrollment;
use App\Models\StudentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class StudentController extends Controller
{
    /** Relasi standar yang selalu dimuat untuk resource siswa. */
    private function relations(): array
    {
        return [
            'status',
            'enrollments.classroom.gradeLevel',
            'enrollments.classroom.major',
        ];
    }

    public function index(Request $request)
    {
        $query = Student::query()
            ->with($this->relations())
            ->withCount('faceDescriptors');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        if ($classroomId = (int) $request->query('classroom_id', 0)) {
            $query->whereHas('enrollments', fn ($q) => $q->where('is_active', true)->where('classroom_id', $classroomId));
        }

        if ($statusCode = trim((string) $request->query('status', ''))) {
            $query->whereHas('status', fn ($q) => $q->where('code', $statusCode));
        }

        $perPage = (int) $request->query('per_page', 10) ?: 10;
        $perPage = max(1, min($perPage, 100));

        return StudentResource::collection(
            $query->orderBy('full_name')->paginate($perPage)->withQueryString(),
        );
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $statusId = StudentStatus::where('code', $data['status'] ?? 'aktif')->value('id');

        $student = Student::create([
            'nis' => $data['nis'],
            'full_name' => $data['full_name'],
            'status_id' => $statusId,
        ]);

        $this->syncEnrollment($student, $data['classroom_id'] ?? null, $data['academic_year_id'] ?? null);

        return (new StudentResource(
            $student->load($this->relations())->loadCount('faceDescriptors'),
        ))->response()->setStatusCode(201);
    }

    public function show(Student $student): StudentResource
    {
        return new StudentResource(
            $student->load($this->relations())->loadCount('faceDescriptors'),
        );
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $data = $request->validated();

        $studentFields = Arr::only($data, ['nis', 'full_name']);
        if (array_key_exists('status', $data)) {
            $studentFields['status_id'] = StudentStatus::where('code', $data['status'])->value('id');
        }

        $student->update($studentFields);

        if (! empty($data['classroom_id'])) {
            $this->syncEnrollment($student, $data['classroom_id'], $data['academic_year_id'] ?? null);
        }

        return new StudentResource(
            $student->load($this->relations())->loadCount('faceDescriptors'),
        );
    }

    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(['message' => 'Siswa berhasil dihapus.']);
    }

    public function import(ImportStudentsRequest $request): JsonResponse
    {
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);

        $statusAktifId = StudentStatus::where('code', 'aktif')->value('id');
        $activeYearId = AcademicYear::where('is_active', true)->value('id');

        $classroomMap = [];
        foreach (Classroom::with(['gradeLevel', 'major'])->get() as $classroom) {
            $key = $this->classroomKey($classroom->gradeLevel?->name, $classroom->major?->code, $classroom->rombel);
            $classroomMap[$key] = $classroom->id;
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $record = array_combine(array_slice($header, 0, count($row)), $row) ?: [];
            $nis = trim((string) ($record['nis'] ?? ''));

            if ($nis === '') {
                $errors[] = "Baris {$rowNumber}: kolom nis kosong.";

                continue;
            }

            $student = Student::updateOrCreate(
                ['nis' => $nis],
                [
                    'full_name' => trim((string) ($record['full_name'] ?? '')) ?: $nis,
                    'status_id' => $statusAktifId,
                ],
            );

            $student->wasRecentlyCreated ? $created++ : $updated++;

            $key = $this->classroomKey($record['grade_level'] ?? null, $record['major'] ?? null, $record['rombel'] ?? null);
            $classroomId = $classroomMap[$key] ?? null;

            if ($classroomId && $activeYearId) {
                StudentClassEnrollment::updateOrCreate(
                    ['student_id' => $student->id, 'academic_year_id' => $activeYearId],
                    ['classroom_id' => $classroomId, 'is_active' => true],
                );
            }
        }

        fclose($handle);

        return response()->json([
            'data' => ['created' => $created, 'updated' => $updated, 'errors' => $errors],
        ]);
    }

    private function syncEnrollment(Student $student, ?int $classroomId, ?int $academicYearId): void
    {
        if (! $classroomId) {
            return;
        }

        $yearId = $academicYearId ?? AcademicYear::where('is_active', true)->value('id');
        if (! $yearId) {
            return;
        }

        StudentClassEnrollment::updateOrCreate(
            ['student_id' => $student->id, 'academic_year_id' => $yearId],
            ['classroom_id' => $classroomId, 'is_active' => true],
        );
    }

    private function classroomKey($gradeLevel, $major, $rombel): string
    {
        return strtoupper(trim((string) $gradeLevel)).'|'.strtoupper(trim((string) $major)).'|'.(int) $rombel;
    }
}
