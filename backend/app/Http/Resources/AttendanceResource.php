<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendance_date' => $this->attendance_date?->format('Y-m-d'),
            'checked_at' => $this->checked_at,
            'match_distance' => $this->match_distance,
            'note' => $this->note,
            'status' => $this->whenLoaded('status', fn () => [
                'code' => $this->status->code,
                'label' => $this->status->label,
            ]),
            'method' => $this->whenLoaded('method', fn () => [
                'code' => $this->method->code,
                'label' => $this->method->label,
            ]),
            'session' => $this->whenLoaded('session', fn () => [
                'code' => $this->session->code,
                'label' => $this->session->label,
            ]),
            'student' => StudentResource::make($this->whenLoaded('student')),
        ];
    }
}
