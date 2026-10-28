<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registry Pengaturan
    |--------------------------------------------------------------------------
    |
    | Sumber default, tipe, dan aturan validasi untuk setting skalar yang
    | disimpan di tabel `app_settings`. Nilai aktif dibaca lewat
    | `App\Services\SettingsService`; config ini hanya berisi definisi.
    |
    | Kunci memakai format bertitik agar mudah dikelompokkan di UI.
    |
    */

    'attendance.self_scan_enabled' => [
        'type' => 'bool',
        'default' => true,
        'group' => 'attendance',
        'rules' => ['boolean'],
    ],

    'face.match_threshold' => [
        'type' => 'float',
        'default' => (float) env('FACE_MATCH_THRESHOLD', 0.6),
        'group' => 'face',
        'rules' => ['numeric', 'between:0.3,0.8'],
    ],

    'face.samples_per_student' => [
        'type' => 'int',
        'default' => (int) env('FACE_SAMPLES_PER_STUDENT', 3),
        'group' => 'face',
        'rules' => ['integer', 'between:1,10'],
    ],

    'notification.wa_enabled' => [
        'type' => 'bool',
        'default' => true,
        'group' => 'notification',
        'rules' => ['boolean'],
    ],

];
