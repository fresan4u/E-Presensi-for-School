<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notifikasi WhatsApp Wali Murid (service wa-bot / Baileys)
    |--------------------------------------------------------------------------
    |
    | Backend tidak mengirim chat secara langsung: setiap pesan ditaruh di
    | tabel `wa_outboxes` lalu diteruskan ke service Node `wa-bot/` lewat
    | HTTP (header `x-api-secret`). Matikan saklar ini untuk menonaktifkan
    | notifikasi tanpa mengubah kode.
    |
    */

    'enabled' => env('WA_ENABLED', false),

    // Alamat service wa-bot & secret bersama untuk autentikasi HTTP.
    'url' => env('WA_BOT_URL', 'http://127.0.0.1:3005'),
    'secret' => env('WA_BOT_SECRET', ''),
    'timeout' => (int) env('WA_TIMEOUT', 20),

    // Batas percobaan kirim per pesan (dicek `wa:retry-failed`).
    'max_attempts' => (int) env('WA_MAX_ATTEMPTS', 5),

    /*
    | Template pesan absensi per sesi. Placeholder yang tersedia:
    |   :wali :sesi :nama :nis :tanggal :jam :kelas :status :catatan :foto
    | Baris yang hasil substitusinya kosong otomatis dibuang dari pesan.
    |
    | Kunci array = kode sesi (`masuk`/`pulang`). Kunci `attendance` dipakai
    | sebagai cadangan bila sebuah sesi belum punya template sendiri, sehingga
    | sesi baru yang ditambahkan admin tetap terkirim dengan format umum.
    | Baris `Status:` sengaja tidak ditulis pada sesi pulang: absensi pulang
    | selalu berstatus "hadir", sehingga menampilkannya justru membingungkan.
    */
    'templates' => [
        'masuk' => <<<'TEMPLATE'
Bapak/Ibu :wali,
Ananda :nama (:nis) telah tiba di sekolah
📅 :tanggal
🕐 pukul :jam WIB
🏫 :kelas
Status: :status
:catatan
:foto
TEMPLATE,

        'pulang' => <<<'TEMPLATE'
Bapak/Ibu :wali,
Ananda :nama (:nis) telah pulang dari sekolah
📅 :tanggal
🕐 pukul :jam WIB
🏫 :kelas
:catatan
:foto
TEMPLATE,

        'attendance' => <<<'TEMPLATE'
Bapak/Ibu :wali,
informasi absensi :sesi :nama (:nis)
📅 :tanggal
🕐 pukul :jam WIB
🏫 :kelas
Status: :status
:catatan
:foto
TEMPLATE,
    ],

];
