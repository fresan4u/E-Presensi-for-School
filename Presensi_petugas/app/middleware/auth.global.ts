/** Route yang boleh dibuka tanpa login. */
const PUBLIC_ROUTES = ['/login', '/scan']

export default defineNuxtRouteMiddleware((to) => {
  const { isLoggedIn } = useAuth()

  // Form login hanya untuk yang belum masuk; sudah masuk? langsung ke dashboard petugas.
  if (to.path === '/login') {
    if (isLoggedIn.value) {
      return navigateTo('/petugas', { replace: true })
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
