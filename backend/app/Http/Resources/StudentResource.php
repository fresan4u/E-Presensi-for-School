<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeEnrollment = $this->relationLoaded('enrollments')
            ? $this->enrollments->firstWhere('is_active', true)
            : null;

        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'full_name' => $this->full_name,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'code' => $this->status->code,
                'label' => $this->status->label,
            ]),
            'classroom' => ($activeEnrollment && $activeEnrollment->relationLoaded('classroom') && $activeEnrollment->classroom)
                ? new ClassroomResource($activeEnrollment->classroom)
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
}
