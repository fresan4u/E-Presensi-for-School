import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const currentDir = dirname(fileURLToPath(import.meta.url))

/**
 * Layer bersama (@presensi/shared)
 * ------------------------------------------------------------------
 * Berisi komponen UI/tabel/layout + composable + util yang dipakai
 * bersama oleh Presensi_admin dan Presensi_petugas.
 *
 * Nuxt tidak mengizinkan path relatif di nuxt.config sebuah layer
 * (di-resolve ke project pemakai). Karena itu semua path di sini
 * memakai path absolut dari `join(currentDir, ...)`.
 *
 * Alias `~/`/`@/` di dalam file layer juga di-resolve ke project
 * pemakai, jadi file layer HARUS memakai path relatif untuk import
 * antar-file di dalam layer.
 */
export default defineNuxtConfig({
  $meta: { name: 'shared' },
  components: [
    { path: join(currentDir, 'app/components/layout'), prefix: 'layout' },
    { path: join(currentDir, 'app/components/ui'), pathPrefix: false },
    { path: join(currentDir, 'app/components/table'), pathPrefix: false },
  ],
})
