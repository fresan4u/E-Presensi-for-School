<script setup lang="ts">
useHead({ title: 'Riwayat Absensi · Absensi Digital' })

const router = useRouter()
const api = useApi()
const { fetchMe } = useStudentAuth()

const loading = ref(true)
const errorMessage = ref('')
/** Riwayat absensi sesi masuk (terbaru lebih dulu). */
const daftar = ref<ApiAttendance[]>([])

// Jangan keluar aplikasi jika tidak ada history (mis. dibuka langsung via URL).
function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/siswa')
}

const rekap = computed(() => {
  const hitung = (kode: string) => daftar.value.filter((a) => a.status.code === kode).length
  return {
    hadir: hitung('hadir'),
    terlambat: hitung('terlambat'),
    alpa: hitung('alpa'),
  }
})

const kartuRekap = computed(() => [
  { label: 'Hadir', nilai: rekap.value.hadir, warna: 'text-brand-600', bg: 'bg-brand-50' },
  { label: 'Terlambat', nilai: rekap.value.terlambat, warna: 'text-amber-600', bg: 'bg-amber-50' },
  { label: 'Alpa', nilai: rekap.value.alpa, warna: 'text-danger-600', bg: 'bg-danger-50' },
])

async function muat() {
  loading.value = true
  errorMessage.value = ''
  try {
    const profil = await fetchMe()
    if (!profil) return

    const res = await api.studentAttendances({ session: 'masuk', per_page: 100 })
    daftar.value = res.data
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  } finally {
    loading.value = false
  }
}

onMounted(muat)
</script>

<template>
  <div class="min-h-dvh bg-slate-50 pb-nav">
    <!-- ── Navbar atas ── -->
    <header
      class="sticky top-0 z-20 flex items-center gap-1 border-b border-slate-100 bg-white px-4 py-3"
    >
      <button
        type="button"
        class="-ml-1 flex size-10 items-center justify-center rounded-full text-slate-700 transition active:bg-slate-100"
        aria-label="Kembali"
        @click="goBack"
      >
        <AppIcon name="chevron-left" :size="24" :stroke-width="2" />
      </button>
      <h1 class="text-base font-bold text-slate-900">Riwayat Absensi</h1>
    </header>

    <main class="px-5 pt-5">
      <!-- ── Rekap kehadiran ── -->
      <section>
        <h2 class="section-title">Rekap Kehadiran</h2>
        <p class="mt-1 text-xs text-slate-400">Sesi masuk · {{ daftar.length }} catatan</p>

        <div class="mt-3 grid grid-cols-3 gap-3">
          <div
            v-for="kartu in kartuRekap"
            :key="kartu.label"
            class="rounded-2xl bg-white p-4 text-center shadow-sm"
          >
            <p class="text-2xl font-extrabold" :class="kartu.warna">{{ kartu.nilai }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">{{ kartu.label }}</p>
          </div>
        </div>
      </section>

      <!-- ── Daftar absensi per tanggal ── -->
      <section class="mt-7">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="section-title">Daftar Absensi</h2>
          <span class="text-xs font-medium text-slate-400">{{ daftar.length }} catatan</span>
        </div>

        <ul
          v-if="daftar.length"
          class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white"
        >
          <li
            v-for="item in daftar"
            :key="item.id"
            class="flex items-center gap-3 px-4 py-3.5"
          >
            <span
              class="flex size-10 shrink-0 items-center justify-center rounded-xl"
              :class="gayaStatus(item.status.code).ikonBox"
            >
              <AppIcon
                :name="gayaStatus(item.status.code).ikon"
                :size="20"
                :stroke-width="2.4"
              />
            </span>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-800">
                {{ tanggalPendek(item.attendance_date) }}
              </p>
              <p class="text-xs text-slate-400">
                {{ waktu(item.checked_at) }} · {{ item.method.label }}
              </p>
            </div>

            <span
              class="rounded-full px-2.5 py-1 text-[11px] font-bold"
              :class="gayaStatus(item.status.code).badge"
            >
              {{ item.status.label }}
            </span>
          </li>
        </ul>

        <p
          v-else-if="errorMessage"
          class="rounded-2xl bg-danger-50 px-4 py-3 text-xs font-medium text-danger-600"
          role="alert"
        >
          {{ errorMessage }}
          <button type="button" class="ml-1 underline" @click="muat">Coba lagi</button>
        </p>

        <p
          v-else-if="loading"
          class="rounded-2xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-400"
        >
          Memuat riwayat…
        </p>

        <p
          v-else
          class="rounded-2xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-400"
        >
          Belum ada riwayat absensi.
        </p>
      </section>
    </main>

    <SiswaBottomnav active="riwayat" />
  </div>
</template>
