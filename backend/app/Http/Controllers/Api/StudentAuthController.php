<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentLoginRequest;
use App\Http\Requests\UpdateStudentPinRequest;
use App\Http\Resources\StudentProfileResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autentikasi portal siswa (app mobile) memakai NIS + PIN.
 *
 * PIN adalah faktor rahasia untuk login; verifikasi wajah dipakai terpisah
 * saat absensi (lihat AttendanceController::scanSelf). Endpoint ini memakai
 * token Sanctum — hanya `login` yang publik.
 */
class StudentAuthController extends Controller
{
    /**
     * Relasi standar untuk profil siswa.
     *
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'status',
            'enrollments.classroom.gradeLevel',
            'enrollments.classroom.major',
        ];
    }

    public function login(StudentLoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $student = Student::query()
            ->where('nis', $data['nis'])
            ->first();

        if (! $student || ! $student->pin || ! hash_equals((string) $student->pin, (string) $data['pin'])) {
            return response()->json(['message' => 'NIS atau PIN salah.'], 401);
        }

        if (! $student->status || $student->status->code !== 'aktif') {
            return response()->json(['message' => 'Akun siswa tidak aktif.'], 403);
        }

        $token = $student->createToken('mobile-student')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'student' => new StudentProfileResource($this->loadProfile($student)),
            ],
        ]);
    }

    public function me(Request $request): StudentProfileResource
    {
        return new StudentProfileResource($this->loadProfile($request->user()));
    }

    public function updatePin(UpdateStudentPinRequest $request): JsonResponse
    {
        $student = $request->user();
        $data = $request->validated();

        // PIN hanya boleh diganti SEKALI (saat login pertama, ketika masih PIN
        // bawaan). Setelah `pin_changed_at` terisi, endpoint ini tertutup
        // permanen supaya siswa tidak bisa mengganti PIN berulang kali.
        if ($student->pin_changed_at !== null) {
            return response()->json([
                'message' => 'PIN hanya dapat diganti sekali dan sudah pernah diubah.',
                'code' => 'pin_already_changed',
            ], 403);
        }

        if (! hash_equals((string) $student->pin, (string) $data['current_pin'])) {
            return response()->json(['message' => 'PIN saat ini salah.'], 422);
        }

        if (hash_equals((string) $student->pin, (string) $data['pin'])) {
            return response()->json(['message' => 'PIN baru tidak boleh sama dengan PIN lama.'], 422);
        }

        $student->update([
            'pin' => $data['pin'],
            'must_change_pin' => false,
            'pin_changed_at' => now(),
        ]);

        return response()->json([
            'message' => 'PIN berhasil diperbarui.',
            'data' => new StudentProfileResource($this->loadProfile($student)),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesi diakhiri.']);
    }

    /**
     * Muat relasi yang dipakai StudentProfileResource.
     */
    private function loadProfile(Student $student): Student
    {
        return $student->load($this->relations())->loadCount('faceDescriptors');
    }
}
