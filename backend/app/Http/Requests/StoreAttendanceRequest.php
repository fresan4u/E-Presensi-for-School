<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'session' => ['required', 'string', 'exists:attendance_sessions,code'],
            'status' => ['required', 'string', 'exists:attendance_statuses,code'],
            'method' => ['nullable', 'string', 'exists:attendance_methods,code'],
            'attendance_date' => ['nullable', 'date'],
            'checked_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
