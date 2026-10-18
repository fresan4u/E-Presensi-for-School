/** Route yang boleh dibuka tanpa login. */
const PUBLIC_ROUTES = ['/login']

export default defineNuxtRouteMiddleware((to) => {
  const { isLoggedIn, user } = useAuth()

  // Form login hanya untuk yang belum masuk; sudah masuk? langsung ke home role-nya.
  if (to.path === '/login') {
    if (isLoggedIn.value) {
      // Petugas diarahkan ke aplikasi terpisah (Presensi_petugas).
      if (user.value?.role === 'Petugas') {
        return navigateTo(useRuntimeConfig().public.petugasUrl, { external: true })
      }
      return navigateTo('/', { replace: true })
    }
    return
  }

  if (PUBLIC_ROUTES.includes(to.path)) {
    return
  }

  // Route terlindungi lainnya wajib login.
  if (!isLoggedIn.value) {
    return navigateTo('/login', { replace: true })
  }
})
