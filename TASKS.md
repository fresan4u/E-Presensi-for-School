# TASK: Halaman "Rekap Absensi & Laporan" (app Petugas)

Halaman untuk **petugas** memfilter rekap kehadiran per kelas pada tanggal tertentu,
menyalinnya ke buku manual, dan menghubungi wali murid via WhatsApp.

Spesifikasi ini sudah **disesuaikan dengan struktur & data nyata** di monorepo
(lihat "Penyesuaian") serta mematuhi **aturan 1NF** dan **etika/privasi data**.

## 1. Lokasi & Integrasi
- File: `Presensi_petugas/app/pages/petugas/rekap-harian.vue`
- `definePageMeta({ layout: 'petugas', title: 'Rekap Absensi' })`
- Tambah item menu **"Rekap Absensi"** (`/petugas/rekap-harian`) di
  `Presensi_petugas/app/layouts/petugas.vue` (sidebar desktop + bottom-nav mobile),
  pakai ikon `ClipboardList`.
- Pakai komponen shared: `base-card`, `data-table`, `base-input`, `base-button`,
  `pagination`. Data lewat `useApi()` (`#layers/shared/...`) — jangan markup mentah.

## 2. Penyesuaian (WAJIB dibaca)
| Spec awal | Diganti jadi | Alasan |
| --- | --- | --- |
| `pages/admin/rekap-harian.vue` | `Presensi_petugas/.../petugas/rekap-harian.vue` | Isinya "digunakan petugas"; absensi = app petugas |
| Filter **Angkatan** (2024/2025/2026) | dihapus (cukup **Jurusan → Kelas**) | Tidak ada `angkatan` di DB; mempercepat alur petugas |
| Jurusan contoh RPL/TKJ | **RPL / ANM / DKV** (3 jurusan) | Ikut data nyata (lihat §3c) |
| Status 4 (Hadir/Izin/Sakit/Alpa) | **5 status** (+ Terlambat) | `attendance_statuses` |
| Tombol WA ke `no_wali` | Tabel **`guardians` + consent** | Tidak ada data kontak wali |
| Export Excel | `.xlsx` via **SheetJS `xlsx`** | Permintaan |
| "Alpa" | **"Alpa (belum absen)"** | Alpa = turunan (siswa tanpa baris absensi) |

## 3. Prasyarat Backend (kerjakan lebih dulu)

### 3a. Tabel `guardians` (memenuhi 1NF)
Migration baru `..._create_guardians_table.php`:
- `id`
- `student_id` FK → `students` (`cascadeOnDelete`)
- `name` (string)
- `relationship` (string: ayah/ibu/wali) — atomik
- `phone` (string, digit saja/E.164) — **satu nomor per baris**
- `is_primary` (boolean, default false)
- `consent_at` (timestamp nullable) — **wajib terisi agar boleh dihubungi**
- `timestamps`
- `unique(['student_id', 'phone'])`

Model `Guardian` + relasi `Student::guardians()`, plus `GuardianFactory`.
> Dilarang menyimpan beberapa nomor dalam satu kolom, atau menaruh nomor wali
> di `students`. Tidak ada kolom gabungan/berulang.

### 3c. Struktur kelas (seed) & data uji
- Jurusan (`majors`): **RPL** (Rekayasa Perangkat Lunak), **ANM** (Animasi), **DKV** (Desain Komunikasi Visual).
- Setiap jurusan punya **6 kelas** (X, XI, XII × 2 rombel) → **18 kelas** (`ClassroomSeeder`).
- **Tidak ada seeder siswa/wali dummy** (dihapus agar DB tidak terisi data contoh).
  Roster siswa & wali dibuat lewat halaman admin / API saat dibutuhkan.
- Data uji feature test memakai factory: `StudentFactory` & `GuardianFactory`.

### 3b. Endpoint rekap
`GET /api/v1/attendances/recap?date=YYYY-MM-DD&classroom_id=ID[&academic_year_id=ID]`
- Ambil semua siswa **aktif** yang ter-enrollment di kelas tsb (tahun ajaran aktif).
- Left-join absensi sesi `masuk` pada tanggal tsb.
- Status default `alpa` bila siswa tidak punya baris absensi (belum absen).
- Sertakan guardian `is_primary` **hanya jika `consent_at` tidak null**.
- Respons: `data` = daftar siswa `{ id, nis, full_name, status, checked_at, note, guardian }`
  + `summary { total, hadir, terlambat, izin, sakit, alpa }`, plus info `classroom` & `date`
  (e.g. `X RPL 1`, `2026/2027`).
Method `recap()` di `AttendanceController` + route + `api.recap()` di `useApi`.

## 4. Spesifikasi UI
### Layout
- Root: `<div class="flex flex-col gap-6">` (padding sudah dari layout petugas).
- Latar `bg-slate-50` (dari layout).

### Kartu Filter (cascading)
- `base-card` judul **"Filter Data Absensi"**, `mb-6`.
- Grid responsif `grid gap-4 md:grid-cols-3 xl:grid-cols-5`:
  1. **Tanggal** (`type="date"`, default hari ini).
  2. **Tahun Ajaran** (opsional; default yang aktif) — `/academic-years`.
  3. **Jurusan** RPL/ANM/DKV — pilih jurusan lebih dulu.
  4. **Kelas** — terkunci sampai Jurusan dipilih; berisi **6 kelas** jurusan tsb
     (X/XI/XII × 2 rombel), label `room.name`.
- Pojok kanan bawah area filter: tombol **Export Excel**
  (`base-button`, border/teks hijau emerald + ikon `Download`).

### Empty State
- Bila **Kelas belum dipilih**: area tengah (`flex flex-col items-center justify-center py-16`),
  ikon `FolderOpen` abu besar + teks:
  "Silakan pilih tanggal, tingkat, jurusan, dan kelas terlebih dahulu untuk melihat daftar murid".

### Tabel Data (muncul bila Kelas sudah dipilih)
- `base-card` + info: **"Menampilkan rekap kelas {Nama Kelas} — {Tanggal}"**.
- `data-table` **4 kolom**:
  1. **NIS** — nomor induk siswa, teks abu gelap.
  2. **Nama Lengkap** — `font-medium`.
  3. **Status** — badge membulat via `STATUS_META` (hadir=emerald, terlambat=amber,
     izin=sky, sakit=slate, alpa=red). Label `alpa` → "Alpa (belum absen)".
  4. **Aksi (Kontak Wali)**.
- **Logika tombol WhatsApp** (kolom Aksi):
  - Muncul hanya jika status ∈ {izin, sakit, alpa, terlambat} **dan** guardian primary
    punya `consent_at` **dan** `phone` ada.
  - Tag `<a target="_blank" rel="noopener" href="https://wa.me/{phone}?text={pesan}">`,
    tombol `bg-emerald-500 hover:bg-emerald-600 text-white rounded-md px-3 py-1 text-sm
    inline-flex items-center gap-2` + ikon `MessageCircle`.
  - Nomor **disensor** di tampilan (mis. `0812••••89`); nomor asli hanya di `href`.
  - Bila kontak belum ada/ber-consent: teks kecil abu **"Kontak wali belum tersedia"**
    (jangan tampilkan tombol palsu/disabled).
- **Pesan WA**: `Halo Bapak/Ibu wali dari {nama}, menginformasikan bahwa siswa tersebut
  pada {tanggal} berstatus {status}. Mohon konfirmasinya.` (URL-encode).
- Tampilkan seluruh siswa 1 kelas tanpa pagination agar mudah disalin.

### Export Excel (.xlsx)
- Tambah dependency **`xlsx` (SheetJS CE)** ke `Presensi_petugas/package.json`.
- Generate di client dari data yang tampil.
- Nama file: `rekap-{kelas}-{tanggal}.xlsx`.
- Kolom: **NIS, Nama, Status, Tanggal** (tanpa kolom kontak wali / PII).
- Tombol non-aktif sampai Kelas dipilih.

## 5. Aturan 1NF (jangan dilanggar)
- Semua kolom bernilai **atomik**; 1 baris `guardians` = 1 nomor = 1 siswa.
- Kelas, jurusan, tingkat, tahun ajaran disimpan sebagai **FK/referensi**, bukan teks gabungan.
- Dilarang menyimpan daftar (nomor/kelas/status) dalam satu kolom.

## 6. Etika & Privasi
- Hanya petugas login (middleware auth + layout petugas) yang bisa mengakses.
- Hanya kontak wali **ber-consent** yang dipakai; nomor disensor di UI; jangan di-log.
- Export **tidak** memuat nomor kontak.
- Data siswa hanya untuk tujuan absensi; **jangan mengarang** nomor wali.

## 7. Acceptance Criteria
- [x] Menu "Rekap Absensi" aktif di sidebar & bottom-nav petugas.
- [x] Cascade Jurusan → Kelas (Kelas terkunci sampai Jurusan dipilih).
- [x] Empty state tampil sebelum Kelas dipilih.
- [x] Tabel 4 kolom (NIS, Nama, Status, Aksi) + waktu absen; siswa tanpa absensi berstatus "Alpa (belum absen)".
- [x] Tombol WA hanya untuk kontak ber-consent; nomor disensor.
- [x] Export `.xlsx` valid tanpa kolom kontak.
- [x] Migration `guardians` lulus & `npm run typecheck:petugas` hijau.
- [x] Feature test `tests/Feature/AttendanceRecapTest.php` (roster penuh, default alpa, consent, validasi).
- [x] E2E `Presensi_admin/tests/recap.e2e.mjs` (mandiri: cascade, roster = API, alpa, fallback wali, export .xlsx).

---

## 8. Portal Siswa (app Mobile, `:3002`) — Fase 2

### 8a. Identitas & data siswa
- **NIS** = satu-satunya nomor identitas siswa (wajib & unik).
- Data siswa ditampilkan minimal: **NIS, nama lengkap, kelas** (tidak ada username,
  nomor wali, dsb. di portal siswa).
- Impor CSV keyed by `nis` (header lama `nisn` tidak dipakai).

### 8b. Autentikasi vs verifikasi kehadiran (dipisah)
- **PIN = autentikasi.** Login mobile memakai **NIS + PIN**. PIN awal = NIS,
  disimpan ter-hash di `students.pin` + flag `students.must_change_pin`; siswa
  **wajib ganti PIN saat login pertama** (diarahkan otomatis ke `/siswa/ganti-pin`).
- **Wajah = verifikasi kehadiran**, bukan login: descriptor dicocokkan **1:1 hanya
  dengan wajah terdaftar milik akun yang sedang login** → mencegah titip absen.
- `auth:sanctum` membungkus route portal siswa **dan** (sejak Fase 3) seluruh
  endpoint operasional admin/petugas. Route publik hanya login (`/auth/login`,
  `/student/login`) dan `/attendance/scan` (kiosk).

### 8c. Endpoint portal siswa
| Method | Path | Keterangan |
| --- | --- | --- |
| POST | `/api/v1/student/login` | Login NIS + PIN → token Sanctum (throttle 10/menit) |
| GET | `/api/v1/student/me` | Profil siswa login (`auth:sanctum`) |
| PUT | `/api/v1/student/pin` | Ganti PIN (`auth:sanctum`) |
| POST | `/api/v1/student/logout` | Cabut token (`auth:sanctum`) |
| POST | `/api/v1/student/attendance/scan` | Absensi wajah 1:1 (`auth:sanctum`, throttle 30/menit) |

`GET /api/v1/attendances` diberi filter `student_id` (dipakai beranda/riwayat).

### 8d. Hardening
- Kolom `descriptor` **tidak** diekspos di API (`StudentFaceDescriptor` hidden).
- Throttle pada login (anti brute-force PIN) dan scan.
- Token siswa di cookie `presensi-student-token`; `useApi` hanya memasang header
  `Authorization` untuk path `/student/*` sehingga app admin/petugas tidak pernah
  mengirim token siswa (cookie tidak ter-scope per port).

### 8e. Frontend
- `Presensi_mobile` memakai layer `@presensi/shared` dan berjalan sebagai **SPA**
  (`ssr: false`) karena semua data diambil di klien + butuh kamera.
- Halaman: `siswa/login`, `siswa/ganti-pin`, `siswa/index` (beranda), `siswa/scan`
  (kamera + pilih sesi masuk/pulang), `siswa/berhasil`, `siswa/gagal`,
  `siswa/riwayat`, `siswa/profil`.
- Guard `middleware/student-auth.global.ts` (token + paksa ganti PIN).
- Mock fingerprint/scan palsu **dihapus** (`fingerprintmodal.vue`, util siswa mock)
  karena mensimulasikan absen sukses tanpa wajah.

### 8f. Acceptance Criteria
- [x] Migrasi `add_pin_to_students_table` (kolom `pin`, `must_change_pin` + backfill PIN = NIS).
- [x] `Student` menjadi Authenticatable + `HasApiTokens`, `pin` hashed & hidden.
- [x] Login NIS + PIN; PIN salah ditolak; PIN default memaksa ganti PIN.
- [x] Scan mobile hanya cocok dengan wajah milik akun yang login (1:1, threshold 0.6).
- [x] Beranda/riwayat/profil memakai data nyata (NIS, nama, kelas) + aksi keluar.
- [x] `php artisan test` hijau (42 test) & Pint bersih.
- [x] `npm run typecheck:mobile` hijau.
- [x] E2E `Presensi_mobile/tests/mobile.e2e.mjs` lulus (login → wajib ganti PIN →
      absensi wajah 1:1 → riwayat → profil → keluar).
- [x] E2E admin/petugas lama tetap lulus (login, petugas-crud, face, recap).

---

## 9. Autentikasi Admin/Petugas (Fase 3)

Menghapus login simulasi; panel admin & petugas memakai autentikasi nyata.

### 9a. Backend
- `User` memakai `HasApiTokens`; login `POST /api/v1/auth/login` (email + password)
  mengembalikan token Sanctum + profil (role). Endpoint `GET /auth/me` & `POST /auth/logout`.
- Seluruh endpoint operasional (`students*`, `officers*`, `face-descriptors*`,
  `attendances*`, `meta`, `classrooms`, `majors`, `academic-years`) dibungkus
  `auth:sanctum`. Hanya `POST /attendance/scan` (kiosk) dan route login yang publik.
- **Pemisahan peran.** Token siswa & petugas berbagi guard `sanctum`, jadi grup
  panel dijaga `EnsurePanelUser` (hanya `User`) dan grup siswa `EnsureStudent`
  (hanya `Student`) — token siswa tidak bisa menembus endpoint admin, dan
  sebaliknya.
- **Satu akun admin tetap**, dibuat dari `.env` (`ADMIN_NAME/EMAIL/PASSWORD`,
  fallback `config/presensi.php`). `UserSeeder` menghapus admin lain (idempotent);
  **tidak ada** endpoint tambah/hapus admin.
- `StudentSeeder`: satu siswa demo (NIS/PIN dari `.env`).
- Endpoint baru `GET /student/attendances` (riwayat milik siswa login; `student_id`
  dari token) menggantikan pemakaian `/attendances` oleh app mobile.

### 9b. Frontend
- `useAuth` nyata (token `presensi-auth-token` + profil `presensi-auth-user`);
  `useApi` menambahkan `login/me/logout` dan memasang header sesuai jenis path
  (`/student/*` → token siswa, sisanya → token panel).
- Halaman login admin **tanpa pemilih role** — role ditentukan server; akun petugas
  dialihkan ke app petugas (`:3001`). Login petugas menolak akun non-petugas.

### 9c. Acceptance Criteria
- [x] `UserSeeder` menjamin tepat satu admin; kredensial dari `.env`.
- [x] `POST /auth/login`, `/auth/me`, `/auth/logout` + feature test `AuthApiTest`.
- [x] Semua endpoint operasional `401` tanpa token; `/attendance/scan` tetap publik.
- [x] Halaman login admin tanpa pemilih role; redirect sesuai role dari server.
- [x] `php artisan test` hijau; ketiga `typecheck` hijau; E2E memakai token/login nyata.

---

## Lampiran: Spec awal (arsip)

> Disimpan untuk riwayat. Isi asli `TASKS.md` sebelum penyesuaian.

Tolong buatkan halaman "Rekap Absensi & Laporan" di file pages/admin/rekap-harian.vue menggunakan Nuxt 3 (script setup) dan Tailwind CSS. Halaman ini digunakan petugas untuk memfilter kelas secara spesifik, menyalin data ke buku manual, dan menghubungi wali murid.

Detail Spesifikasi UI:
- Layout Utama: Gunakan latar belakang abu-abu terang (bg-gray-50) dengan padding luas (p-6).
- Area Filter (Cascading Dropdown):
  - Buat sebuah kartu putih (bg-white, p-6, rounded-xl, shadow-sm, mb-6) dengan judul "Filter Data Absensi".
  - Di dalam kartu, buat grid 4 kolom (grid md:grid-cols-4 gap-4) untuk deretan filter berikut:
    1. Input "Pilih Tanggal" (type="date", default hari ini).
    2. Select "Pilih Angkatan" (contoh: 2024, 2025, 2026).
    3. Select "Pilih Jurusan" (contoh: RPL, TKJ). Atur state disabled jika Angkatan belum dipilih.
    4. Select "Pilih Kelas" (contoh: X RPL 1, X RPL 2). Atur state disabled jika Jurusan belum dipilih.
  - Di pojok kanan bawah area filter, tambahkan tombol "Export Excel" (border hijau, teks hijau, flex items-center) untuk mengunduh rekap.
- Empty State (Area Kosong):
  - Jika "Kelas" belum dipilih, tampilkan area kosong di tengah layar (flex justify-center) berisi ikon dokumen/folder abu-abu besar dengan teks "Silakan pilih tanggal, angkatan, jurusan, dan kelas terlebih dahulu untuk melihat daftar murid".
- Area Tabel Data (Tampil jika filter Kelas sudah dipilih):
  - Buat tabel bersih di dalam kartu putih (bg-white, rounded-xl, overflow-hidden, shadow-sm).
  - Di atas tabel, tambahkan teks info "Menampilkan rekap kelas [Nama Kelas] - [Tanggal]".
  - Tabel HANYA memiliki 4 kolom utama agar mudah disalin ke buku fisik:
    1. NIS (teks abu-abu gelap).
    2. Nama Lengkap (teks tebal/font-medium).
    3. Status (gunakan badge/pill membulat: Hijau/Hadir, Kuning/Izin, Biru/Sakit, Merah/Alpa).
    4. Aksi (Kontak Wali).
  - Logika Tombol WhatsApp pada kolom Aksi: Jika status siswa adalah Alpa, Izin, atau Sakit, tampilkan tombol kecil (bg-emerald-500 hover:bg-emerald-600 text-white rounded-md px-3 py-1 text-sm flex items-center gap-2). Tombol ini menggunakan tag <a> dengan target="_blank" dan href="https://wa.me/{no_wali}?text=Halo Bapak/Ibu wali dari {nama_lengkap}, menginformasikan bahwa siswa tersebut hari ini berstatus {status_absen}. Mohon konfirmasinya."
