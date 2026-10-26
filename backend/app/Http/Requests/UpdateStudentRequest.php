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
            // Admin dapat mengganti PIN siswa kapan saja (mis. siswa lupa PIN).
            'pin' => ['nullable', 'string', 'min:4', 'max:32'],
            'status' => ['nullable', 'in:aktif,non_aktif'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'guardians' => ['sometimes', 'array', 'max:5'],
            'guardians.*.name' => ['nullable', 'string', 'max:100'],
            'guardians.*.relationship' => ['nullable', 'string', 'max:50'],
            'guardians.*.phone' => ['required', 'string', 'max:25'],
            'guardians.*.is_primary' => ['nullable', 'boolean'],
        ];
    }
}
