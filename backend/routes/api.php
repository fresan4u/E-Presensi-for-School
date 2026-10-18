<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FaceDescriptorController;
use App\Http\Controllers\Api\OfficerController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StudentAuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Middleware\EnsurePanelUser;
use App\Http\Middleware\EnsureStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'koneksi laravel dan nuxt sukses!']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Semua endpoint operasional (admin & petugas) memakai token Sanctum. Satu-
| satunya endpoint publik selain login adalah `POST /attendance/scan` (kiosk
| absensi tanpa login). Portal siswa memakai NIS + PIN lewat grup terpisah.
*/
Route::prefix('v1')->group(function () {
    /*
     * Login panel admin & petugas (email + password).
     */
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    /*
     * Portal siswa (app mobile): login NIS + PIN.
     */
    Route::post('/student/login', [StudentAuthController::class, 'login'])->middleware('throttle:10,1');

    /*
     * Kiosk absensi publik (`/scan` di app petugas): cocokkan wajah & catat
     * absensi tanpa login. Dibatasi throttle agar tidak disalahgunakan.
     */
    Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->middleware('throttle:30,1');

    /*
     * Sesi panel admin & petugas.
     */
    Route::middleware(['auth:sanctum', EnsurePanelUser::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    /*
     * Operasional admin & petugas (butuh login sebagai User, bukan siswa).
     */
    Route::middleware(['auth:sanctum', EnsurePanelUser::class])->group(function () {
        // Referensi / master data
        Route::get('/meta', [ReferenceController::class, 'meta']);
        Route::get('/classrooms', [ReferenceController::class, 'classrooms']);
        Route::get('/majors', [ReferenceController::class, 'majors']);
        Route::get('/academic-years', [ReferenceController::class, 'academicYears']);

        // Siswa
        Route::post('/students/import', [StudentController::class, 'import']);
        Route::apiResource('students', StudentController::class);

        // Petugas (kelola akun petugas dari panel admin)
        Route::apiResource('officers', OfficerController::class);

        // Descriptor wajah (registrasi)
        Route::get('/students/{student}/face-descriptors', [FaceDescriptorController::class, 'index']);
        Route::post('/students/{student}/face-descriptors', [FaceDescriptorController::class, 'store']);
        Route::delete('/students/{student}/face-descriptors/{descriptor}', [FaceDescriptorController::class, 'destroy']);

        // Absensi (rekap & pencatatan manual)
        Route::get('/attendances/recap', [AttendanceController::class, 'recap']);
        Route::get('/attendances', [AttendanceController::class, 'index']);
        Route::post('/attendances', [AttendanceController::class, 'store']);
        Route::put('/attendances/{attendance}', [AttendanceController::class, 'update']);
    });

    /*
     * Portal siswa (app mobile): sesi, riwayat, & absensi mandiri.
     */
    Route::middleware(['auth:sanctum', EnsureStudent::class])->group(function () {
        Route::get('/student/me', [StudentAuthController::class, 'me']);
        Route::put('/student/pin', [StudentAuthController::class, 'updatePin']);
        Route::post('/student/logout', [StudentAuthController::class, 'logout']);

        // Riwayat absensi milik siswa yang login (student_id dari token).
        Route::get('/student/attendances', [StudentAttendanceController::class, 'index']);

        // Absensi mandiri: wajah diverifikasi 1:1 terhadap akun yang login.
        Route::post('/student/attendance/scan', [AttendanceController::class, 'scanSelf'])
            ->middleware('throttle:30,1');
    });
});
