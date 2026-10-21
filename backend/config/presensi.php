<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Admin Tetap
    |--------------------------------------------------------------------------
    |
    | Sistem hanya memiliki SATU akun admin. Akun ini dibuat ulang (idempotent)
    | oleh `UserSeeder` dan tidak bisa ditambah/dihapus lewat API — tidak ada
    | endpointnya. Ganti kredensial lewat .env.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Andi Pratama'),
        'email' => env('ADMIN_EMAIL', 'andi.pratama@sekolah.id'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Akun Petugas Demo
    |--------------------------------------------------------------------------
    |
    | Hanya untuk memudahkan uji coba. Akun petugas lain dibuat admin lewat
    | panel "Kelola Petugas".
    |
    */

    'petugas' => [
        'name' => env('PETUGAS_NAME', 'Bagus Wijaya'),
        'email' => env('PETUGAS_EMAIL', 'bagus.wijaya@sekolah.id'),
        'password' => env('PETUGAS_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Akun Siswa Demo
    |--------------------------------------------------------------------------
    |
    | Login portal siswa memakai NIS + PIN. PIN disimpan ter-hash. Default di
    | sini sama dengan NIS; ganti lewat .env bila perlu.
    |
    */

    'student' => [
        'nis' => env('STUDENT_NIS', '2024000'),
        'full_name' => env('STUDENT_NAME', 'Ahmad Fauzi'),
        'pin' => env('STUDENT_PIN', '2024000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Jendela Waktu Absensi Mandiri (app siswa)
    |--------------------------------------------------------------------------
    |
    | Absensi mandiri lewat HP hanya terbuka pada dua jendela:
    |
    |   - MASUK  : mulai jam 00:00 sampai `self_cutoff` (07:00).
    |              Antara `handover_at` (06:40) dan `self_cutoff` petugas
    |              mengambil alih lewat input manual — siswa yang belum absen
    |              dilayani petugas, bukan lagi dari HP.
    |   - PULANG : mulai jam `pulang_from` (15:00) sampai tengah malam.
    |
    | Di luar dua jendela itu `POST /student/attendance/scan` ditolak dengan
    | kode `attendance_closed`. Sesi TIDAK lagi dipilih siswa: server yang
    | menentukan `masuk` atau `pulang` dari jam, sehingga siswa tidak bisa
    | mengaku absen pulang pada pagi hari.
    |
    | Petugas/admin tetap bisa mencatat absensi kapan saja lewat input manual
    | (`POST /attendances`) untuk kasus khusus seperti pulang cepat karena sakit.
    |
    */

    'attendance' => [
        // Batas terakhir siswa boleh absen mandiri (sesi masuk).
        'self_cutoff' => env('ATTENDANCE_SELF_CUTOFF', '07:00'),

        // Mulai jam ini petugas yang melayani (informasi untuk tampilan).
        'handover_at' => env('ATTENDANCE_HANDOVER_AT', '06:40'),

        // Absensi mandiri sesi pulang dibuka mulai jam ini.
        'pulang_from' => env('ATTENDANCE_PULANG_FROM', '15:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wali Murid Akun Siswa Demo
    |--------------------------------------------------------------------------
    |
    | Satu wali ber-consent dibuat `GuardianSeeder` untuk akun siswa demo
    | supaya notifikasi WhatsApp benar-benar terkirim pada demo.
    |
    */

    'guardian' => [
        'name' => env('GUARDIAN_NAME', 'Budi Santoso'),
        'relationship' => env('GUARDIAN_RELATIONSHIP', 'ayah'),
        'phone' => env('GUARDIAN_PHONE', '081234567890'),
    ],

];
