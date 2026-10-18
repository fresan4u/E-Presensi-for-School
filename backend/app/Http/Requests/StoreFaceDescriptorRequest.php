<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFaceDescriptorRequest extends FormRequest
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
            'sample_no' => ['nullable', 'integer', 'min:1', 'max:3'],
            'quality_score' => ['nullable', 'numeric', 'between:0,1'],
            'model_version' => ['nullable', 'string', 'max:100'],
        ];
    }
}
