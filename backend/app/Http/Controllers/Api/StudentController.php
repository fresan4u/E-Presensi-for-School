<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStudentActionRequest;
use App\Http\Requests\ImportStudentsRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassEnrollment;
use App\Models\StudentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /** Relasi standar yang selalu dimuat untuk resource siswa. */
    private function relations(): array
    {
        return [
            'status',
            'enrollments.academicYear',
            'enrollments.classroom.gradeLevel',
            'enrollments.classroom.major',
            'guardians',
        ];
    }

    public function index(Request $request)
    {
        $query = Student::query()
            ->with($this->relations())
            ->withCount('faceDescriptors');

        $yearId = $this->selectedYearId($request);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('unassigned')) {
            $query->doesntHave('enrollments');
        } elseif ($yearId) {
            $query->whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $yearId));
        }

        if ($classroomId = (int) $request->query('classroom_id', 0)) {
            $query->whereHas('enrollments', function ($q) use ($classroomId, $yearId) {
                $q->where('is_active', true)->where('classroom_id', $classroomId);

                if ($yearId) {
                    $q->where('academic_year_id', $yearId);
                }
            });
        }

        if ($statusCode = trim((string) $request->query('status', ''))) {
            $query->whereHas('status', fn ($q) => $q->where('code', $statusCode));
        }

        $this->applySort($query, $request, $yearId);

        $perPage = (int) $request->query('per_page', 10) ?: 10;
        $perPage = max(1, min($perPage, 100));

        return StudentResource::collection(
            $query->paginate($perPage)->withQueryString(),
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
            // Tanpa PIN eksplisit, model memakai NIS (lihat Student::booted()).
            ...(filled($data['pin'] ?? null) ? ['pin' => $data['pin']] : []),
        ]);

        $this->syncEnrollment($student, $data['classroom_id'] ?? null, $data['academic_year_id'] ?? null);
        $this->syncGuardians($student, $data['guardians'] ?? null);

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

        // Admin mengganti PIN = reset resmi. Buka lagi gerbang ganti PIN sekali
        // supaya siswa bisa menggantinya sendiri setelah login.
        if (filled($data['pin'] ?? null)) {
            $studentFields['pin'] = $data['pin'];
            $studentFields['must_change_pin'] = false;
            $studentFields['pin_changed_at'] = null;
        }

        $student->update($studentFields);

        // Hanya sentuh enrollment tahun yang dipilih. Ini mencegah edit roster
        // tahun lama menimpa kelas siswa pada tahun ajaran yang sedang aktif.
        if (array_key_exists('classroom_id', $data)) {
            $this->syncEnrollment($student, $data['classroom_id'], $data['academic_year_id'] ?? null);
        }

        $this->syncGuardians($student, $data['guardians'] ?? null);

        return new StudentResource(
            $student->load($this->relations())->loadCount('faceDescriptors'),
        );
    }

    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(['message' => 'Siswa berhasil dihapus.']);
    }

    /**
     * Aksi massal per tahun ajaran. Dipakai panel admin untuk menonaktifkan,
     * mengaktifkan kembali, atau menghapus siswa pada satu angkatan/tahun.
     */
    public function bulkAction(BulkStudentActionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $yearId = (int) $data['academic_year_id'];
        $action = $data['action'];
        $all = $request->boolean('all');

        $query = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $yearId));

        if (! $all) {
            $query->whereIn('id', $data['ids'] ?? []);
        }

        $students = $query->get(['id']);

        if ($students->isEmpty()) {
            return response()->json(['message' => 'Tidak ada siswa yang cocok pada tahun ajaran tersebut.'], 422);
        }

        if (! $all && count($data['ids'] ?? []) !== $students->count()) {
            return response()->json([
                'message' => 'Sebagian siswa tidak terdaftar pada tahun ajaran yang dipilih.',
            ], 422);
        }

        $ids = $students->pluck('id');

        if ($action === 'delete') {
            DB::transaction(fn () => Student::query()->whereIn('id', $ids)->delete());
        } else {
            $statusId = StudentStatus::query()
                ->where('code', $action === 'activate' ? 'aktif' : 'non_aktif')
                ->value('id');

            DB::transaction(function () use ($ids, $yearId, $statusId, $action): void {
                Student::query()->whereIn('id', $ids)->update(['status_id' => $statusId]);

                StudentClassEnrollment::query()
                    ->whereIn('student_id', $ids)
                    ->where('academic_year_id', $yearId)
                    ->update(['is_active' => $action === 'activate']);
            });
        }

        $labels = [
            'activate' => 'diaktifkan',
            'deactivate' => 'dinonaktifkan',
            'delete' => 'dihapus',
        ];

        return response()->json([
            'message' => "{$students->count()} siswa berhasil {$labels[$action]}.",
            'data' => ['count' => $students->count(), 'action' => $action],
        ]);
    }

    public function import(ImportStudentsRequest $request): JsonResponse
    {
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);

        $statusAktifId = StudentStatus::where('code', 'aktif')->value('id');
        $yearId = (int) ($request->input('academic_year_id') ?: AcademicYear::activeId());

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

            $record = array_combine(array_slice($header, 0, count($row)), array_slice($row, 0, count($header))) ?: [];
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

            if ($classroomId && $yearId) {
                StudentClassEnrollment::updateOrCreate(
                    ['student_id' => $student->id, 'academic_year_id' => $yearId],
                    ['classroom_id' => $classroomId, 'is_active' => true],
                );
            }

            $this->importGuardian($student, $record);
        }

        fclose($handle);

        return response()->json([
            'data' => ['created' => $created, 'updated' => $updated, 'errors' => $errors],
        ]);
    }

    private function syncEnrollment(Student $student, ?int $classroomId, ?int $academicYearId): void
    {
        $yearId = $academicYearId ?? AcademicYear::activeId();

        if (! $yearId) {
            return;
        }

        if (! $classroomId) {
            StudentClassEnrollment::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $yearId)
                ->update(['is_active' => false]);

            return;
        }

        StudentClassEnrollment::updateOrCreate(
            ['student_id' => $student->id, 'academic_year_id' => $yearId],
            ['classroom_id' => $classroomId, 'is_active' => true],
        );
    }

    /**
     * Impor wali dari kolom CSV opsional.
     *
     * Kolom yang dibaca: `guardian_name`, `guardian_relationship`, `guardian_phone`.
     * Baris tanpa `guardian_phone` diabaikan — wali tanpa nomor tidak berguna
     * untuk notifikasi WhatsApp.
     *
     * @param  array<string, mixed>  $record
     */
    private function importGuardian(Student $student, array $record): void
    {
        $phone = trim((string) ($record['guardian_phone'] ?? ''));

        if ($phone === '') {
            return;
        }

        $student->guardians()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => trim((string) ($record['guardian_name'] ?? '')) ?: 'Wali Murid',
                'relationship' => trim((string) ($record['guardian_relationship'] ?? '')) ?: 'wali',
                'is_primary' => true,
                'consent_at' => now(),
            ],
        );
    }

    private function classroomKey($gradeLevel, $major, $rombel): string
    {
        return strtoupper(trim((string) $gradeLevel)).'|'.strtoupper(trim((string) $major)).'|'.(int) $rombel;
    }

    /** Tahun ajaran yang dipilih pada query string (`all`/kosong = semua). */
    private function selectedYearId(Request $request): ?int
    {
        $value = $request->query('academic_year_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applySort(Builder $query, Request $request, ?int $yearId): void
    {
        $sort = (string) $request->query('sort', 'name');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort === 'nis') {
            $query->orderBy('nis', $direction);

            return;
        }

        if ($sort === 'classroom') {
            $query
                ->orderBy($this->enrollmentOrderSubquery($yearId, 'grade_levels.level'), $direction)
                ->orderBy($this->enrollmentOrderSubquery($yearId, 'majors.code'), $direction)
                ->orderBy($this->enrollmentOrderSubquery($yearId, 'classrooms.rombel'), $direction)
                ->orderBy('full_name');

            return;
        }

        $query->orderBy('full_name', $direction);
    }

    private function enrollmentOrderSubquery(?int $yearId, string $column): Builder
    {
        return StudentClassEnrollment::query()
            ->join('classrooms', 'classrooms.id', '=', 'student_class_enrollments.classroom_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'classrooms.grade_level_id')
            ->leftJoin('majors', 'majors.id', '=', 'classrooms.major_id')
            ->whereColumn('student_class_enrollments.student_id', 'students.id')
            ->when($yearId, fn (Builder $query) => $query->where('student_class_enrollments.academic_year_id', $yearId))
            ->select($column)
            ->limit(1);
    }

    /**
     * Sinkronkan daftar wali murid.
     *
     * Semantik: field `guardians` yang dikirim berarti "ganti seluruh daftar
     * wali". Field yang tidak dikirim (null) berarti "jangan sentuh wali yang
     * ada" — supaya update NIS/nama tidak ikut menghapus nomor wali.
     *
     * @param  array<int, array<string, mixed>>|null  $guardians
     */
    private function syncGuardians(Student $student, ?array $guardians): void
    {
        if ($guardians === null) {
            return;
        }

        $student->guardians()->delete();

        foreach ($guardians as $index => $guardian) {
            $phone = trim((string) ($guardian['phone'] ?? ''));

            if ($phone === '') {
                continue;
            }

            $student->guardians()->create([
                'name' => trim((string) ($guardian['name'] ?? '')) ?: 'Wali Murid',
                'relationship' => trim((string) ($guardian['relationship'] ?? '')) ?: 'wali',
                'phone' => $phone,
                'is_primary' => (bool) ($guardian['is_primary'] ?? $index === 0),
                'consent_at' => now(),
            ]);
        }
    }
}
