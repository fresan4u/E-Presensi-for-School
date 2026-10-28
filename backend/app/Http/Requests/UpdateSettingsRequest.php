<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'attendance' => ['sometimes', 'array'],
            'attendance.self_scan_enabled' => ['sometimes', 'boolean'],

            'face' => ['sometimes', 'array'],
            'face.match_threshold' => ['sometimes', 'numeric', 'between:0.3,0.8'],
            'face.samples_per_student' => ['sometimes', 'integer', 'between:1,10'],

            'notification' => ['sometimes', 'array'],
            'notification.wa_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
