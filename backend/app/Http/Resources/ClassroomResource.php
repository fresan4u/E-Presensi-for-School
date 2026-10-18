<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rombel' => $this->rombel,
            'grade_level' => $this->whenLoaded('gradeLevel', fn () => [
                'id' => $this->gradeLevel->id,
                'name' => $this->gradeLevel->name,
                'level' => $this->gradeLevel->level,
            ]),
            'major' => $this->whenLoaded('major', fn () => [
                'id' => $this->major->id,
                'code' => $this->major->code,
                'name' => $this->major->name,
            ]),
        ];
    }
}
