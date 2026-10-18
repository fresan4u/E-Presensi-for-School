<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nis' => ['required', 'string', 'max:20', 'unique:students,nis'],
            'full_name' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'in:aktif,non_aktif'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ];
    }
}
