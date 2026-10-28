<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAttendanceSchedulesRequest extends FormRequest
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
            'schedules' => ['required', 'array', 'size:7'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,7', 'distinct'],
            'schedules.*.is_active' => ['required', 'boolean'],
            'schedules.*.masuk_start' => ['required', 'date_format:H:i'],
            'schedules.*.masuk_end' => ['required', 'date_format:H:i'],
            'schedules.*.handover_at' => ['nullable', 'date_format:H:i'],
            'schedules.*.pulang_start' => ['required', 'date_format:H:i'],
            'schedules.*.pulang_end' => ['required', 'date_format:H:i'],
            'schedules.*.late_after' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Validasi lintas field: urutan jam, jendela masuk vs pulang, dan posisi
     * handover/late_after di dalam jendela masuk.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rows = $this->input('schedules', []);

            if (! is_array($rows) || count($rows) !== 7) {
                return;
            }

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $masukStart = $this->minutes($row['masuk_start'] ?? null);
                $masukEnd = $this->minutes($row['masuk_end'] ?? null);
                $pulangStart = $this->minutes($row['pulang_start'] ?? null);
                $pulangEnd = $this->minutes($row['pulang_end'] ?? null);
                $handover = $this->minutes($row['handover_at'] ?? null);
                $lateAfter = $this->minutes($row['late_after'] ?? null);

                if ($masukStart !== null && $masukEnd !== null && $masukStart >= $masukEnd) {
                    $validator->errors()->add(
                        "schedules.{$index}.masuk_end",
                        'Jam selesai masuk harus lebih besar dari jam mulai masuk.',
                    );
                }

                if ($pulangStart !== null && $pulangEnd !== null && $pulangStart >= $pulangEnd) {
                    $validator->errors()->add(
                        "schedules.{$index}.pulang_end",
                        'Jam selesai pulang harus lebih besar dari jam mulai pulang.',
                    );
                }

                if ($masukEnd !== null && $pulangStart !== null && $masukEnd > $pulangStart) {
                    $validator->errors()->add(
                        "schedules.{$index}.pulang_start",
                        'Jendela pulang tidak boleh dimulai sebelum jendela masuk selesai.',
                    );
                }

                if ($handover !== null && $masukStart !== null && $masukEnd !== null
                    && ($handover < $masukStart || $handover > $masukEnd)) {
                    $validator->errors()->add(
                        "schedules.{$index}.handover_at",
                        'Jam serah-terima harus berada di dalam jendela masuk.',
                    );
                }

                if ($lateAfter !== null && $masukStart !== null && $masukEnd !== null
                    && ($lateAfter < $masukStart || $lateAfter > $masukEnd)) {
                    $validator->errors()->add(
                        "schedules.{$index}.late_after",
                        'Batas terlambat harus berada di dalam jendela masuk.',
                    );
                }
            }
        });
    }

    private function minutes(mixed $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
