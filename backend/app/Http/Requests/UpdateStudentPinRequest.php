<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentPinRequest extends FormRequest
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
            'current_pin' => ['required', 'string', 'max:32'],
            'pin' => ['required', 'string', 'min:4', 'max:32', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_pin.required' => 'PIN saat ini wajib diisi.',
            'pin.required' => 'PIN baru wajib diisi.',
            'pin.min' => 'PIN baru minimal :min karakter.',
            'pin.confirmed' => 'Konfirmasi PIN baru tidak cocok.',
        ];
    }
}
