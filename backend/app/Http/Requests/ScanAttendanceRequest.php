<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descriptor' => ['required', 'array', 'size:128'],
            'descriptor.*' => ['numeric'],
            'session' => ['required', 'string', 'exists:attendance_sessions,code'],

            // Opsional: foto bukti hasil capture frame kamera (data URI JPEG
            // base64, maks. ~3 MB ter-decode) — dilampirkan ke chat wali.
            'photo' => [
                'nullable', 'string', 'max:4000000',
                'regex:/^data:image\/(jpeg|jpg);base64,[A-Za-z0-9+\/=]+$/',
            ],
        ];
    }
}
