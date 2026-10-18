tolong buatkan halaman operasional mesin absensi di file pages/admin/mesin-absen.vue menggunakan nuxt 3 script setup dan tailwind css. tambahkan definepagemeta({ layout: false }) agar halaman ini berdiri sendiri tanpa sidebar admin.

detail spesifikasi ui:

    gunakan layout penuh layar (min-h-screen bg-gray-50 flex flex-col).

    bagian header (atas): buat navbar simpel dengan latar putih (bg-white p-4 shadow-sm flex justify-between items-center). sisi kiri berisi teks "mesin absensi operasional". sisi kanan berisi teks jam digital berjalan berukuran besar (misal: 06:45:22 wib) dan tanggal hari ini.

    area statistik cepat (bawah header): buat area sejajar berisi 3 kotak kartu (bg-white rounded-xl p-4 flex-1) untuk menampilkan: total siswa, sudah hadir, dan belum hadir.

    area konten utama (bawah statistik): gunakan grid layout (grid lg:grid-cols-3 gap-6 p-6 flex-1).

    kolom kiri (kamera pemindai, mengambil col-span-2): buat kotak besar berlatar hitam (bg-gray-900 rounded-2xl overflow-hidden relative) sebagai tempat render video webcam. tambahkan overlay garis pembidik wajah berwarna hijau di tengahnya.

    kolom kanan (live log absensi, col-span-1): buat kotak putih (bg-white rounded-2xl p-5 flex flex-col h-full). beri judul "riwayat scan terbaru". di bawahnya buat daftar (list) yang bisa di-scroll (overflow-y-auto). setiap baris (item) menampilkan avatar siswa, nama lengkap, jam kedatangan, dan teks status "berhasil" berwarna hijau.