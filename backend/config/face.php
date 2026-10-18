<?php

return [
    /*
    | Panjang vektor descriptor face-api.js (128 angka).
    */
    'descriptor_length' => 128,

    /*
    | Jumlah sampel wajah yang diambil per siswa saat registrasi.
    */
    'samples_per_student' => 3,

    /*
    | Ambang batas jarak Euclidean untuk dianggap cocok.
    | face-api.js memakai ~0.6 sebagai default FaceMatcher.
    */
    'match_threshold' => (float) env('FACE_MATCH_THRESHOLD', 0.6),

    'model_version' => 'face-api.js@0.22.2/tinyFaceDetector+faceRecognition',
];
