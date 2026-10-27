/**
 * Guard sesi siswa (app mobile).
 *
 * - Selain halaman login, semua route `/siswa/**` wajib punya token login.
 * - Selama PIN masih default (`must_change_pin`), siswa dipaksa mengganti PIN
 *   di `/siswa/ganti-pin` sebelum memakai aplikasi.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const { isLoggedIn, student, fetchMe, logout } = useStudentAuth()

  // Halaman login: siswa yang sudah punya sesi langsung diarahkan ke beranda.
  if (to.path === '/siswa/login') {
    return isLoggedIn.value ? navigateTo('/siswa') : undefined
  }

  if (!to.path.startsWith('/siswa')) return

  if (!isLoggedIn.value) return navigateTo('/siswa/login')

  // Profil dibutuhkan untuk cek `must_change_pin`. Ambil di klien saja supaya
  // SSR tidak memanggil API dengan URL relatif.
  if (import.meta.client && !student.value) {
    try {
      await fetchMe()
    } catch (error) {
      const failure = error as {
        status?: number
        statusCode?: number
        response?: { status?: number }
      }
      const status = failure.status ?? failure.statusCode ?? failure.response?.status

      // Hanya 401/403 yang berarti sesi benar-benar berakhir. Gangguan jaringan
      // atau backend yang sedang restart tidak boleh menghapus sesi pengguna.
      if (status === 401 || status === 403) {
        await logout()
        return navigateTo('/siswa/login')
      }
    }
  }

  if (student.value?.must_change_pin && to.path !== '/siswa/ganti-pin') {
    return navigateTo('/siswa/ganti-pin')
  }
})
