<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaceDescriptorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'sample_no' => $this->sample_no,
            'model_version' => $this->model_version,
            'quality_score' => $this->quality_score,
            'is_active' => $this->is_active,
            'captured_at' => $this->created_at,
        ];
    }
}
