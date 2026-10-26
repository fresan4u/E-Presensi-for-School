import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  /*
   * Portal siswa adalah aplikasi klien murni: semua data diambil di klien
   * (`onMounted`) memakai token di cookie + kamera `getUserMedia`. Menonaktifkan
   * SSR menghindari mismatch hydration (server tidak tahu profil siswa) dan
   * menjaga `$fetch` baseURL relatif tetap aman.
   */
  ssr: false,

  /*
   * Kode bersama (@presensi/shared): composable useApi/useFaceScanner/
   * useStudentAuth + util. Halaman khusus siswa tetap di app ini.
   */
  extends: ['@presensi/shared'],

  css: ['~/assets/css/main.css'],

  // App mobile (siswa) di port 3002 agar tidak bentrok dengan admin (3000) & petugas (3001).
  devServer: {
    port: 3002,
  },

  /*
   * Base URL backend Laravel. Relatif (`/api/v1`) supaya browser cukup bicara
   * ke origin Nuxt, lalu Nuxt mem-proxy ke Laravel lewat `routeRules`.
   * Timpa lewat env NUXT_PUBLIC_API_BASE bila backend ada di host lain.
   */
  runtimeConfig: {
    public: {
      apiBase: '/api/v1',
      // Koordinat & radius sekolah buat geofencing (desimal). Nilai ini hanya
      // fallback: app siswa mengambil pengaturan terbaru dari endpoint
      // /school-location saat halaman scan dibuka.
      schoolLat: process.env.NUXT_PUBLIC_SCHOOL_LAT || '-6.188410',
      schoolLng: process.env.NUXT_PUBLIC_SCHOOL_LNG || '106.924823',
      schoolRadius: process.env.NUXT_PUBLIC_SCHOOL_RADIUS_METER || '100',
    },
  },

  /*
   * Proxy server-side ke backend Laravel (127.0.0.1:8000).
   * Ganti target lewat env NUXT_API_PROXY bila backend pindah host/port.
   */
  routeRules: {
    '/api/**': {
      proxy: `${process.env.NUXT_API_PROXY || 'http://127.0.0.1:8000'}/api/**`,
    },
  },

  vite: {
    plugins: [tailwindcss()],
  },

  app: {
    head: {
      title: 'Absensi Digital',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover' },
        { name: 'theme-color', content: '#005a3f' },
        { name: 'description', content: 'Aplikasi absensi siswa berbasis face recognition & fingerprint.' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        },
      ],
    },
  },
})
