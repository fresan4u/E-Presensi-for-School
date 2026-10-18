# Presensi — Absensi Siswa dengan Pengenalan Wajah

Aplikasi presensi sekolah: **1 backend Laravel 13** (API) + **3 frontend Nuxt 4**
(admin, petugas, dan mobile siswa). Alur utama: admin mendaftarkan wajah siswa
(3 sampel descriptor 128 angka dari `face-api.js`), lalu siswa melakukan absensi
dengan memindai wajah di kiosk.

## Struktur Repo (monorepo, npm workspaces)

```
Presensi_Project/
├── backend/                  # Laravel 13 (API v1, MySQL, Sanctum siap pakai)
├── Presensi_admin/           # Nuxt 4 — panel admin (data siswa & registrasi wajah)
├── Presensi_petugas/         # Nuxt 4 — panel petugas (mesin absensi, scan, data absensi)
├── Presensi_mobile/          # Nuxt 4 — app siswa (Level 1: UI + data mock)
├── packages/
│   └── shared/               # Nuxt layer @presensi/shared (dipakai admin & petugas)
│                             #   komponen UI/tabel/layout + composable + util
└── package.json              # workspaces + skrip dev/build
```

`Presensi_admin` dan `Presensi_petugas` memakai kode bersama dari layer
`packages/shared` (`extends: ['@presensi/shared']`). `Presensi_mobile` punya
design system sendiri (brand hijau, khusus mobile) dan berdiri sendiri.

## Port & Aplikasi

| Aplikasi           | Port   | URL                       | Isi                                                    |
| ------------------ | ------ | ------------------------- | ------------------------------------------------------ |
| Backend (Laravel)  | `8000` | `http://127.0.0.1:8000`   | API `/api/v1`                                          |
| `Presensi_admin`   | `3000` | `http://localhost:3000`   | Panel admin: data siswa + registrasi wajah             |
| `Presensi_petugas` | `3001` | `http://localhost:3001`   | Panel petugas: mesin absensi + kiosk `/scan` + data absensi |
| `Presensi_mobile`  | `3002` | `http://localhost:3002`   | App siswa (mock, Level 1)                              |

> **Pembagian fitur (jangan ditukar lagi).**
> - **Admin** (`Presensi_admin`, `:3000`): dashboard **statistik/overview** kehadiran
>   sekolah (`app/pages/index.vue`), **Data Siswa** (`/siswa`), dan **Registrasi Wajah**
>   (`/registrasi-wajah`). Menu sidebar: Dashboard, Data Siswa, Registrasi Wajah.
> - **Petugas** (`Presensi_petugas`, `:3001`): dashboard **monitoring harian**
>   (`app/pages/petugas/index.vue`), **Mesin Absensi** (`/petugas/mesin-absen`),
>   **Kiosk Scan** publik (`/scan`), **Rekap Absensi** (`/petugas/rekap-harian`), dan
>   **Data Absensi** (`/petugas/data-absensi`).
>   Layout **responsif**: sidebar (Beranda, Mesin Absensi, Rekap Absensi, Data Absensi)
>   di layar lebar, bottom-nav di HP. Halaman Mesin Absensi & Scan berjalan **fullscreen**
>   (mode kiosk) dan dilengkapi **konfirmasi sebelum keluar** (guard) agar tidak
>   tidak sengaja keluar saat melayani absensi.
>
> Aturan singkat: **admin = master data siswa**, **petugas = operasional absensi**.

## Prasyarat

- PHP 8.3+ dan Composer
- Node.js 20+ dan npm
- MySQL (database `testss` untuk dev)

## Menjalankan Backend

```sh
cd backend
composer install
cp .env.example .env          # lalu sesuaikan DB_DATABASE/DB_USERNAME/DB_PASSWORD
php artisan key:generate
php artisan migrate           # skema inti (14 migrasi)
php artisan db:seed           # data referensi + 1 admin + 1 petugas + 1 siswa demo
php artisan serve --host=0.0.0.0 --port=8000
```

`db:seed` mengisi data referensi (status, kelas, tahun ajaran) plus akun demo
(**satu admin tetap**, satu petugas, satu siswa). Data **absensi** dan **sampel
wajah** sengaja dikosongkan dan diisi lewat aplikasi (registrasi wajah dan mesin
absensi).

API tersedia di `http://127.0.0.1:8000/api/v1`.

## Menjalankan Frontend (monorepo)

```sh
npm install                   # di root: memasang semua workspace sekaligus

npm run dev:admin             # Presensi_admin  → http://localhost:3000
npm run dev:petugas           # Presensi_petugas → http://localhost:3001
npm run dev:mobile            # Presensi_mobile → http://localhost:3002
```

Jalankan ketiganya di terminal terpisah (backend harus aktif lebih dulu).

**Cara API terhubung.** `Presensi_admin` & `Presensi_petugas` mengakses backend
lewat proxy Nitro: browser bicara ke origin Nuxt (`apiBase = /api/v1`), lalu
`routeRules` meneruskan `/api/**` ke `http://127.0.0.1:8000` (server-side). Jadi
CORS tidak perlu diatur dan app tetap jalan di host apa pun (localhost / IP LAN).
Timpa target proxy bila backend pindah host/port:

```sh
NUXT_API_PROXY=http://192.168.1.10:8000 npm run dev:admin
```

`Presensi_mobile` (Level 1) belum memanggil API — semua data mock — tetapi slot
proxy `/api/**` sudah disiapkan untuk naik ke Level 2.

> Kamera browser hanya aktif di `localhost` atau HTTPS. Membuka app lewat
> `http://<IP-LAN>:3000` tidak akan memberi izin kamera; pakai HTTPS/tunnel bila
> perlu kamera dari perangkat lain.

## Akun Demo

Dibuat oleh `UserSeeder`/`StudentSeeder` (backend). Kredensial bisa diubah lewat
`.env` (`ADMIN_*`, `PETUGAS_*`, `STUDENT_*`):

| Segmen  | Login via              | Email / NIS            | Password / PIN |
| ------- | ---------------------- | ---------------------- | -------------- |
| Admin   | panel admin (`:3000`)  | `andi.pratama@sekolah.id` | `password`  |
| Petugas | panel petugas (`:3001`) | `bagus.wijaya@sekolah.id` | `password` |
| Siswa   | app mobile (`:3002`)   | NIS `2024000`          | PIN `2024000`  |

> **Hanya ada satu akun admin** — dibuat dari `.env` (fallback di
> `config/presensi.php`) dan tidak bisa ditambah/dihapus lewat API. Akun
> **petugas** lain dibuat admin lewat menu **Kelola Petugas**. Akun **siswa**
> dibuat lewat menu **Data Siswa**.

Login panel admin/petugas kini **nyata** (memanggil `POST /api/v1/auth/login`,
token Sanctum) dan **role ditentukan server** — halaman login admin tidak lagi
punya pemilih role. Akun admin tetap di dashboard admin (`:3000`); akun petugas
otomatis dialihkan ke aplikasi petugas (`:3001`). Portal siswa di app mobile
memakai **NIS + PIN**.

## Alur Data Siswa & Registrasi Wajah (app Admin, `:3000`)

1. Masuk di `http://localhost:3000` (login admin) → dashboard admin (statistik).
2. Menu **Data Siswa** (`/siswa`): kelola data pokok siswa — tambah/edit/hapus,
   import CSV, cari & paginasi. Setelah menambah siswa, klik **Daftarkan Wajah**
   untuk langsung lanjut ke tab registrasi.
3. Halaman **Registrasi Wajah** (`/registrasi-wajah`): cari & pilih siswa. Kamera
   menyala setelah siswa dipilih.
4. Klik **Mulai Pemindaian** → saat wajah terdeteksi, klik **Simpan Sampel**.
5. Ulangi hingga 3 sampel (maksimum). Sampel bisa dihapus & diambil ulang.

Menu **Kelola Petugas** (`/kelola-petugas`): kelola akun petugas — tambah, ubah,
aktif/nonaktif, dan hapus. Akun yang sedang login tidak bisa menghapus/menonaktifkan
dirinya sendiri.

> Menghapus siswa juga menghapus sampel wajah & riwayat absensinya (relasi cascade).
> Demi privasi, endpoint `face-descriptors` **tidak** mengembalikan vektor descriptor —
> hanya metadata (nomor sampel, waktu, kualitas).

Descriptor disimpan sebagai **kolom JSON per sampel** di `student_face_descriptors`
(unique `student_id + sample_no`, maksimum `config('face.samples_per_student')`).

## Alur Absensi (app Petugas, `:3001`)

- Kiosk publik `/scan` (tanpa login) atau mesin operasional petugas `/petugas/mesin-absen`
  (halaman layar penuh, wajib login, mendukung sesi masuk & pulang). Keduanya mode kiosk:
  bila ditinggalkan (tombol **Keluar**, tombol back, atau tutup/reload tab) akan muncul
  konfirmasi agar pemindaian tidak terhenti tanpa sengaja.
- Browser mengirim descriptor ke `POST /api/v1/attendance/scan`.
- Server mencocokkan dengan **jarak Euclidean**, threshold `config('face.match_threshold')` (0.6),
  hanya untuk siswa berstatus **aktif**.
- Hasil: `404 face_not_recognized` bila tidak ada yang cocok; selain itu record
  absensi dibuat dengan `firstOrCreate` (idempotent per `student + tanggal + sesi`).
- Status sesi `masuk`: **terlambat** bila melewati `boundary_time` sesi (seed 07:30), selain itu **hadir**.
  Sesi `pulang`: **hadir**.

## App Siswa (`Presensi_mobile`, `:3002`) — Level 2

Aplikasi siswa berbasis mobile (viewport khusus, brand hijau) yang sudah
terhubung ke backend. Memakai layer `@presensi/shared` (composable & util sama
dengan admin/petugas) dan kamera nyata lewat `useFaceScanner`.

- **Login: NIS + PIN.** NIS adalah satu-satunya nomor identitas.
- **PIN awal = NIS.** Setelah login pertama siswa **wajib mengganti PIN**
  (diarahkan otomatis ke halaman Ganti PIN); PIN juga bisa diubah dari Profil.
- **Wajah = verifikasi kehadiran, bukan login.** Scan wajah dicocokkan **1:1
  hanya dengan wajah terdaftar milik akun yang sedang login**, sehingga tidak
  bisa titip absen.
- Sesi login memakai token Sanctum di cookie `presensi-student-token`
  (dipasang otomatis sebagai header `Authorization` oleh `useApi`).
- Data siswa ditampilkan minimal: **NIS, nama lengkap, kelas**.
- Registrasi wajah siswa tetap dilakukan petugas/admin lewat panel admin.

## Format CSV Impor Siswa

Endpoint: `POST /api/v1/students/import` (multipart, field `file`, `csv`/`txt`).

Header (huruf kecil, urutan bebas setelah `nis`):

```csv
nis,full_name,grade_level,major,rombel,status
9001,Budi Santoso,X,RPL,1,aktif
9002,Siti Aminah,XI,DKV,2,aktif
```

- `nis` **wajib** dan unik; baris tanpa `nis` dilewati dan dicatat di `errors`.
- Kelas dibentuk dari kunci `grade_level|major|rombel`, mis. `X|RPL|1`.
  Jurusan tersedia: **RPL, ANM, DKV**. Kelas harus sudah ada (lihat
  `ClassroomSeeder`); bila tidak cocok, baris tetap dibuat tanpa enrollment.
- Import bersifat **upsert** berdasarkan `nis` (mengembalikan `created`, `updated`, `errors`).

## Struktur Kelas (Seeder)

`php artisan migrate:fresh --seed` hanya mengisi **master data**:

- **3 jurusan**: RPL (Rekayasa Perangkat Lunak), ANM (Animasi), DKV (Desain Komunikasi Visual).
- **18 kelas** = 3 jurusan × 6 kelas (X/XI/XII × 2 rombel).

Tidak ada seeder wali dummy; hanya **satu siswa demo** (`StudentSeeder`) untuk
mencoba login portal mobile. Roster siswa lain dibuat lewat halaman admin / API
saat dibutuhkan, dan data uji otomatis memakai factory (`StudentFactory`,
`GuardianFactory`).

## Ringkasan Endpoint API (`/api/v1`)

| Method | Path | Keterangan |
| ------ | ---- | ---------- |
| POST | `/auth/login` | Login panel admin/petugas (email + password) → token Sanctum (throttle 10/menit) |
| GET | `/auth/me` | Profil user panel yang login (butuh token) |
| POST | `/auth/logout` | Cabut token panel yang aktif |
| GET | `/meta` | Lookup status/metode/sesi + konfigurasi wajah |
| GET | `/classrooms` `/majors` `/academic-years` | Master data |
| GET/POST | `/students` | List (search & filter) / tambah |
| GET/PUT/PATCH/DELETE | `/students/{student}` | Detail / ubah / hapus |
| POST | `/students/import` | Impor massal CSV |
| GET/POST | `/officers` | List (search & filter) / tambah akun petugas |
| GET/PUT/PATCH/DELETE | `/officers/{officer}` | Detail / ubah / aktif-nonaktif / hapus petugas |
| GET/POST | `/students/{student}/face-descriptors` | List / simpan sampel wajah |
| DELETE | `/students/{student}/face-descriptors/{descriptor}` | Hapus sampel |
| POST | `/attendance/scan` | Cocokkan wajah & catat absensi (**publik**, kiosk) |
| GET | `/attendances/recap` | Rekap harian per kelas (roster penuh, default `alpa`) |
| GET/POST | `/attendances` | Rekap (filter) / catat manual |
| PUT | `/attendances/{attendance}` | Ubah status/catatan |
| POST | `/student/login` | Login portal siswa (NIS + PIN) → token Sanctum (throttle 10/menit) |
| GET | `/student/me` | Profil siswa yang login (butuh token) |
| GET | `/student/attendances` | Riwayat absensi siswa yang login (hanya milik sendiri) |
| PUT | `/student/pin` | Ganti PIN siswa (butuh token) |
| POST | `/student/logout` | Cabut token aktif siswa |
| POST | `/student/attendance/scan` | Absensi mandiri: wajah diverifikasi 1:1 ke siswa login (throttle 30/menit) |

> **Semua endpoint operasional admin/petugas terkunci `auth:sanctum`.** Login
> panel memakai `POST /auth/login`; token dikirim app lewat header
> `Authorization` (cookie `presensi-auth-token`). Satu-satunya endpoint publik
> adalah `POST /attendance/scan` (kiosk `/scan`) dan route login
> (`/auth/login`, `/student/login`). Token siswa dan petugas memakai guard yang
> sama, jadi endpoint panel dijaga `EnsurePanelUser` (hanya akun `User`) dan
> endpoint siswa dijaga `EnsureStudent` (hanya akun `Student`).

Filter `GET /attendances` mendukung `student_id`, `date`, `session`, `status`,
`per_page` — dipakai app mobile untuk beranda/riwayat.

## Pengujian

```sh
# Backend (feature test API — pakai database test terpisah "presensi_test")
cd backend && php artisan test

# Typecheck frontend (dari root)
npm run typecheck:admin
npm run typecheck:petugas
npm run typecheck:mobile

# E2E (butuh backend :8000 + admin :3000 + petugas :3001 + mobile :3002 jalan)
cd Presensi_admin
node tests/login.e2e.mjs      # login gateway → admin (:3000) & petugas (:3001)
node tests/petugas-crud.e2e.mjs # CRUD akun petugas di panel admin
node tests/face.e2e.mjs       # registrasi wajah (admin) → scan & mesin absen (petugas)
node tests/recap.e2e.mjs      # rekap absensi petugas → fallback wali + export .xlsx

cd ../Presensi_mobile
node tests/mobile.e2e.mjs     # login NIS+PIN → wajib ganti PIN → absensi wajah 1:1 → riwayat → keluar
```

`tests/face.e2e.mjs` menguji alur wajah penuh di browser memakai kamera palsu
Chromium yang diumpan `tests/fixtures/face.jpg` (video Y4M dibuat otomatis saat
pertama dijalankan): registrasi 3 sampel di `Presensi_admin` → absen di `/scan`
(petugas) → absen di `/petugas/mesin-absen` (petugas). Karena `db:seed` tidak mengisi
siswa, kedua test
e2e membuat **sendiri** data yang dibutuhkan lewat API sebelum dijalankan. Test ini
menghapus seluruh descriptor lalu mendaftar ulang satu siswa, jadi jalankan hanya
di database pengembangan.
