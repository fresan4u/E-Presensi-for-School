/**
 * Guard "keluar halaman" untuk mode kiosk (Mesin Absensi & Scan Kiosk).
 *
 * - Menahan navigasi dalam aplikasi (`onBeforeRouteLeave`) lalu meminta
 *   konfirmasi, supaya petugas/siswa tidak sengaja keluar dari mode kiosk.
 * - Menampilkan peringatan native browser saat tab ditutup / di-reload
 *   (`beforeunload`).
 *
 * Pemakaian di halaman:
 *   const { leaveModalOpen, requestLeave, confirmLeave, cancelLeave } = useLeaveGuard()
 *
 * Lalu render `<modal v-model="leaveModalOpen" ...>` dan hubungkan tombol
 * "Keluar" ke `requestLeave('/petugas')`.
 */
export function useLeaveGuard() {
  const router = useRouter()

  /** Status modal konfirmasi. */
  const leaveModalOpen = ref(false)
  /** Tujuan yang ditunda sampai user mengonfirmasi. */
  const pendingTarget = ref<string | null>(null)
  /** Lewati guard satu kali setelah user mengonfirmasi. */
  let bypass = false

  onBeforeRouteLeave((to) => {
    if (bypass) {
      bypass = false
      return true
    }

    pendingTarget.value = to.fullPath
    leaveModalOpen.value = true
    return false
  })

  /** Buka dialog konfirmasi secara manual (mis. dari tombol Keluar). */
  const requestLeave = (to = '/petugas') => {
    pendingTarget.value = to
    leaveModalOpen.value = true
  }

  /** Lanjutkan keluar. */
  const confirmLeave = () => {
    leaveModalOpen.value = false
    const to = pendingTarget.value
    pendingTarget.value = null
    if (!to) return

    bypass = true
    router.push(to)
  }

  /** Batalkan keluar (tetap di halaman). */
  const cancelLeave = () => {
    leaveModalOpen.value = false
    pendingTarget.value = null
  }

  const beforeUnloadHandler = (event: BeforeUnloadEvent) => {
    event.preventDefault()
    // Sebagian browser butuh returnValue agar dialog muncul.
    event.returnValue = ''
  }

  onMounted(() => window.addEventListener('beforeunload', beforeUnloadHandler))
  onUnmounted(() => window.removeEventListener('beforeunload', beforeUnloadHandler))

  return { leaveModalOpen, requestLeave, confirmLeave, cancelLeave }
}
