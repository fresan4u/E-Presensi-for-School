import tailwindcss from "@tailwindcss/vite";

export default defineNuxtConfig({
  compatibilityDate: "2025-07-15",
  devtools: { enabled: true },

  // Kode bersama (komponen UI/tabel/layout + composable + util).
  extends: ['@presensi/shared'],

  css: ['~/assets/css/main.css'],

  // App petugas di port 3001 (admin 3000, mobile 3002).
  devServer: {
    port: 3001,
  },

  /*
   * Sama seperti admin: browser bicara ke origin Nuxt, Nitro mem-proxy
   * /api/** ke backend Laravel. Jadi CORS tidak perlu diatur.
   */
  runtimeConfig: {
    public: {
      apiBase: '/api/v1',
      // URL app admin (dipakai pintasan "Panel Admin" di sidebar petugas).
      adminUrl: process.env.NUXT_PUBLIC_ADMIN_URL || 'http://localhost:3000',
    },
  },
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
