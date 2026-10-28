<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStudentActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'action' => ['required', 'in:activate,deactivate,delete'],
            'all' => ['sometimes', 'boolean'],
            'ids' => ['required_without:all', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:students,id'],
        ];
    }
}
