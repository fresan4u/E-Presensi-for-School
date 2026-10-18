<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'nis' => ['sometimes', 'string', 'max:20', Rule::unique('students', 'nis')->ignore($student)],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'status' => ['nullable', 'in:aktif,non_aktif'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ];
    }
}
