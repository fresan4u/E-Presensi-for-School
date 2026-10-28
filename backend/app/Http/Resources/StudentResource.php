<?php

namespace App\Http\Resources;

use App\Models\AcademicYear;
use App\Models\StudentClassEnrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrollment = $this->enrollmentForRequest($request);

        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'full_name' => $this->full_name,
            /*
             * PIN hanya untuk panel admin/petugas yang login sebagai User.
             * Siswa (Student) & kiosk publik tidak pernah menerima PIN, jadi
             * resource ini tetap aman dipakai di AttendanceResource.
             */
            'pin' => $this->when($request->user() instanceof User, fn () => $this->pin),
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'code' => $this->status->code,
                'label' => $this->status->label,
            ]),
            'academic_year' => ($enrollment && $enrollment->relationLoaded('academicYear') && $enrollment->academicYear)
                ? [
                    'id' => $enrollment->academicYear->id,
                    'label' => $enrollment->academicYear->label,
                ]
                : null,
            'enrollment_active' => $enrollment?->is_active,
            'classroom' => ($enrollment && $enrollment->relationLoaded('classroom') && $enrollment->classroom)
                ? new ClassroomResource($enrollment->classroom)
                : null,
            'guardians' => $this->whenLoaded('guardians', fn () => $this->guardians->map(fn ($guardian) => [
                'id' => $guardian->id,
                'name' => $guardian->name,
                'relationship' => $guardian->relationship,
                'phone' => $guardian->phone,
                'is_primary' => (bool) $guardian->is_primary,
                'consent_at' => $guardian->consent_at,
                'biometric_consent_at' => $guardian->biometric_consent_at,
            ])->values()),
            'face_descriptors_count' => $this->face_descriptors_count ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Enrollment yang relevan dengan konteks request.
     *
     * Panel admin mengirim `academic_year_id` agar kelas yang tampil sesuai
     * tahun yang sedang dibuka. Endpoint lain (mobile/absensi) tidak mengirim
     * parameter tersebut, sehingga fallback ke tahun ajaran aktif.
     */
    private function enrollmentForRequest(Request $request): ?StudentClassEnrollment
    {
        if (! $this->relationLoaded('enrollments')) {
            return null;
        }

        $yearId = $request->query('academic_year_id');

        if (! is_numeric($yearId)) {
            $yearId = $request->attributes->get('active_academic_year_id');

            if ($yearId === null) {
                $yearId = AcademicYear::activeId();
                $request->attributes->set('active_academic_year_id', $yearId);
            }
        }

        if ($yearId === null) {
            return null;
        }

        $candidates = $this->enrollments
            ->where('academic_year_id', (int) $yearId);

        return $candidates->firstWhere('is_active', true) ?? $candidates->first();
    }
}
