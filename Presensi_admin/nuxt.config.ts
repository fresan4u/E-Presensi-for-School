import tailwindcss from "@tailwindcss/vite";

export default defineNuxtConfig({
  compatibilityDate: "2025-07-15",
  devtools: { enabled: true },

  /*
   * Kode bersama (komponen UI/tabel/layout + composable + util) ada di layer
   * @presensi/shared (npm workspace). Halaman & layout khusus admin tetap di
   * app ini; layer menyediakan sisanya.
   */
  extends: ['@presensi/shared'],

  css: ['~/assets/css/main.css'],

  // Admin (dashboard + kiosk) di port 3000.
  devServer: {
    port: 3000,
  },

  /*
   * Base URL backend Laravel. Default relatif (`/api/v1`) supaya browser cukup
   * bicara ke origin Nuxt, lalu Nuxt mem-proxy ke Laravel lewat `routeRules` di
   * bawah. Ini membuat aplikasi otomatis jalan di host apa pun (localhost,
   * IP LAN, atau di balik proxy) tanpa perlu mengatur CORS.
   * Timpa lewat env NUXT_PUBLIC_API_BASE bila backend ada di host lain.
   */
  runtimeConfig: {
    public: {
      apiBase: '/api/v1',
      // URL app petugas (dipakai untuk mengalihkan login role Petugas).
      petugasUrl: process.env.NUXT_PUBLIC_PETUGAS_URL || 'http://localhost:3001',
    },
  },
  /*
   * Proxy server-side ke backend Laravel (dijalankan di 127.0.0.1:8000).
   * Menggantikan CORS: request /api/** dari browser diteruskan oleh Nitro.
   * Ganti target lewat env NUXT_API_PROXY bila backend pindah host/port.
   */
  routeRules: {
    '/api/**': {
      proxy: `${process.env.NUXT_API_PROXY || 'http://127.0.0.1:8000'}/api/**`,
    },
  },
  vite: {
    plugins: [
      tailwindcss(),
    ],
  },
});
