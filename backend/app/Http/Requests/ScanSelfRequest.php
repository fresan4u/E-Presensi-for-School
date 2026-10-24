<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Absensi mandiri siswa (app mobile).
 *
 * Berbeda dengan `ScanAttendanceRequest` (kiosk petugas), request ini TIDAK
 * menerima `session`: server menentukannya sendiri dari jam. Field `session`
 * yang terlanjur dikirim klien lama sengaja tidak divalidasi — request tetap
 * diproses dan sesi yang dipakai tetap hasil hitungan server.
 */
class ScanSelfRequest extends FormRequest
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
            'descriptor' => ['required', 'array', 'size:128'],
            'descriptor.*' => ['numeric'],

            // Opsional: foto bukti hasil capture frame kamera (data URI JPEG
            // base64, maks. ~3 MB ter-decode) — dilampirkan ke chat wali.
            'photo' => [
                'nullable', 'string', 'max:4000000',
                'regex:/^data:image\/(jpeg|jpg);base64,[A-Za-z0-9+\/=]+$/',
            ],
            // Koordinat GPS siswa saat absen (buat geofencing/history).
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ];
    }
}
