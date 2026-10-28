# Presensi_mobile — Portal Siswa (Nuxt 4, port 3002)

Aplikasi mobile siswa untuk **absensi digital**: login **NIS + PIN**, wajib ganti
PIN saat login pertama, lalu **absen dengan pemindaian wajah**. Wajah dipakai
untuk **verifikasi kehadiran** (bukan login) dan dicocokkan **1:1 hanya dengan
wajah terdaftar milik akun yang sedang login**, sehingga tidak bisa titip absen.

Aplikasi ini memakai layer `@presensi/shared` (composable `useApi`,
`useStudentAuth`, `useFaceScanner`, util status absensi) dan merupakan **SPA**
(`ssr: false`) karena semua data diambil di klien.

## Menjalankan

```bash
# dari root monorepo
npm run dev:mobile          # http://localhost:3002
npm run typecheck:mobile
npm run build:mobile
```

Butuh backend Laravel di `http://127.0.0.1:8000` (diproxy lewat `routeRules`
`/api/**`, jadi tidak ada CORS). Ganti target proxy dengan env `NUXT_API_PROXY`.

## Alur

1. **Login** (`/siswa/login`) — NIS + PIN. PIN awal = NIS.
2. **Ganti PIN** (`/siswa/ganti-pin`) — otomatis dipaksa selama `must_change_pin`.
3. **Beranda** (`/siswa`) — status absen hari ini + absensi terbaru.
4. **Scan Wajah** (`/siswa/scan`) — sesi ditentukan server dari jadwal hari itu;
   kamera mendeteksi wajah lalu descriptor, foto, koordinat, dan akurasi GPS
   dikirim ke `POST /student/attendance/scan`. Server menolak bila libur,
   hari nonaktif, di luar jendela, atau di luar area (geofence aktif).
5. **Berhasil/Gagal** (`/siswa/berhasil`, `/siswa/gagal`).
6. **Registrasi Wajah** (`/siswa/registrasi-wajah`) — daftarkan 3 sampel wajah,
   dibuka dari kartu di halaman Profil. Kartu itu otomatis hilang setelah semua
   sampel terisi.
7. **Riwayat** (`/siswa/riwayat`) & **Profil** (`/siswa/profil`, ganti PIN & keluar).

Guard route ada di `app/middleware/student-auth.global.ts`: halaman `/siswa/**`
selain login wajib punya token, dan PIN default memaksa ke halaman ganti PIN.

## Endpoint yang dipakai

| Method | Path | Keterangan |
| ------ | ---- | ---------- |
| POST | `/student/login` | Login NIS + PIN → token Sanctum |
| GET | `/student/me` | Profil siswa login |
| PUT | `/student/pin` | Ganti PIN |
| POST | `/student/logout` | Cabut token |
| POST | `/student/attendance/scan` | Absensi wajah 1:1 |
| GET | `/student/face-descriptors` | Sampel wajah siswa login |
| POST | `/student/face-descriptors` | Simpan satu sampel wajah |
| GET | `/attendances?student_id=...` | Riwayat/beranda |

Token disimpan di cookie `presensi-student-token` dan dipasang otomatis sebagai
header `Authorization` oleh `useApi`.

## Model face-api

Model di `public/models` (tiny face detector, landmark 68, face recognition)
dipakai `useFaceScanner`. Kamera browser (`getUserMedia`) hanya berjalan di
`localhost`/HTTPS — untuk uji di HP via IP LAN perlu HTTPS.

## Pengujian

```bash
# backend + server dev harus jalan
node tests/mobile.e2e.mjs
```

E2E memakai kamera palsu Chromium (`tests/fixtures/face.jpg` → video Y4M dibuat
otomatis). Test membuat siswa uji baru lewat API, lalu: guard tanpa sesi → PIN
salah ditolak → login PIN default → wajib ganti PIN → sesi bertahan setelah
reload → absensi wajah 1:1 (descriptor yang tertangkap otomatis didaftarkan agar
scan berhasil) → riwayat → profil → keluar.
