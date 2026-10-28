<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceScheduleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FaceDescriptorController;
use App\Http\Controllers\Api\OfficerController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\SchoolHolidayController;
use App\Http\Controllers\Api\SchoolSettingController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StudentAttendanceController;
use App\Http\Controllers\Api\StudentAuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Middleware\EnsureAdmin;
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
     * Lokasi sekolah publik: app siswa mengambil titik & radius terbaru saat
     * scan, sehingga admin bisa memindahkan titik tanpa redeploy.
     */
    Route::get('/school-location', [SchoolSettingController::class, 'show']);

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
     * Pengaturan global — HANYA admin. Petugas tetap memakai endpoint
     * operasional di atas, tetapi tidak boleh mengubah konfigurasi sekolah.
     */
    Route::middleware(['auth:sanctum', EnsurePanelUser::class, EnsureAdmin::class])->group(function () {
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::get('/settings/audit', [SettingsController::class, 'audit']);

        // Tahun ajaran: baca boleh petugas, tulis hanya admin.
        Route::post('/academic-years', [AcademicYearController::class, 'store']);
        Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update']);
        Route::patch('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate']);
        Route::delete('/academic-years/{academicYear}', [AcademicYearController::class, 'destroy']);

        // Aksi massal siswa per tahun ajaran (nonaktif/aktif/hapus).
        Route::post('/students/bulk-action', [StudentController::class, 'bulkAction']);

        Route::get('/attendance-schedules', [AttendanceScheduleController::class, 'index']);
        Route::put('/attendance-schedules', [AttendanceScheduleController::class, 'update']);

        Route::get('/school-holidays', [SchoolHolidayController::class, 'index']);
        Route::post('/school-holidays', [SchoolHolidayController::class, 'store']);
        Route::put('/school-holidays/{schoolHoliday}', [SchoolHolidayController::class, 'update']);
        Route::delete('/school-holidays/{schoolHoliday}', [SchoolHolidayController::class, 'destroy']);

        // Lokasi sekolah (geofencing absensi mandiri siswa).
        Route::put('/school-location', [SchoolSettingController::class, 'update']);
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

        // Referensi yang dibutuhkan app siswa (jendela absen, status, sesi).
        // `/meta` milik panel tidak bisa dipakai siswa karena middleware
        // EnsurePanelUser, sehingga perlu endpoint khusus di grup siswa.
        Route::get('/student/meta', [ReferenceController::class, 'meta']);

        // Registrasi wajah mandiri (student_id selalu dari token).
        Route::get('/student/face-descriptors', [FaceDescriptorController::class, 'mine']);
        Route::post('/student/face-descriptors', [FaceDescriptorController::class, 'storeMine'])
            ->middleware('throttle:20,1');
        Route::delete('/student/face-descriptors/{descriptor}', [FaceDescriptorController::class, 'destroyMine']);

        // Absensi mandiri: wajah diverifikasi 1:1 terhadap akun yang login.
        Route::post('/student/attendance/scan', [AttendanceController::class, 'scanSelf'])
            ->middleware('throttle:30,1');
    });
});
