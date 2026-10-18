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

];
