<script setup lang="ts">
import { CalendarDays, X } from '@lucide/vue'

useHead({ title: 'Riwayat Absensi · Absensi Digital' })

const router = useRouter()
const api = useApi()
const { fetchMe } = useStudentAuth()

const loading = ref(true)
const errorMessage = ref('')
/** Riwayat absensi sesi masuk, HANYA bulan berjalan (terbaru lebih dulu). */
const daftar = ref<ApiAttendance[]>([])

// Jangan keluar aplikasi jika tidak ada history (mis. dibuka langsung via URL).
function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/siswa')
}

/* ------------------------------------------------------------------ */
/* Bulan berjalan                                                      */
/* ------------------------------------------------------------------ */

/** Satu bulan paling banyak 31 catatan sesi masuk, jadi 31 sudah cukup. */
const BATAS_CATATAN = 31

/** Hari pertama bulan ini (dihitung saat halaman dibuka). */
const bulanIni = new Date(new Date().getFullYear(), new Date().getMonth(), 1)

/** YYYY-MM-DD menurut zona waktu perangkat (bukan UTC). */
const keIso = (tanggal: Date) =>
  `${tanggal.getFullYear()}-${String(tanggal.getMonth() + 1).padStart(2, '0')}-${String(tanggal.getDate()).padStart(2, '0')}`

/** Awalan tanggal bulan ini, mis. "2026-11". */
const kunciBulan = keIso(bulanIni).slice(0, 7)

const labelBulan = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(bulanIni)

const hariIniIso = computed(() => keIso(new Date()))

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

    const res = await api.studentAttendances({ session: 'masuk', per_page: BATAS_CATATAN })

    // Simpan hanya catatan bulan ini; bulan sebelumnya tidak ditampilkan.
    daftar.value = res.data.filter((item) => item.attendance_date.slice(0, 7) === kunciBulan)
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  } finally {
    loading.value = false
  }
}

onMounted(muat)

/* ------------------------------------------------------------------ */
/* Kalender kehadiran (bulan ini saja)                                 */
/* ------------------------------------------------------------------ */

type StatusHari = 'hadir' | 'terlambat' | 'tidak' | 'lain'

const kalenderBuka = ref(false)

const NAMA_HARI = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']

/** Peta tanggal -> status dari catatan absensi (sesi masuk). */
const statusPerTanggal = computed(() => {
  const peta = new Map<string, StatusHari>()
  for (const item of daftar.value) {
    const kode = item.status.code
    const status: StatusHari =
      kode === 'hadir' ? 'hadir' : kode === 'terlambat' ? 'terlambat' : kode === 'alpa' ? 'tidak' : 'lain'
    peta.set(item.attendance_date.slice(0, 10), status)
  }
  return peta
})

const statusHari = (iso: string, hariKe: number): StatusHari | null => {
  const tercatat = statusPerTanggal.value.get(iso)
  if (tercatat) return tercatat

  // Senin–Jumat yang sudah lewat tanpa catatan = tidak melakukan presensi.
  // Hanya dihitung bila siswa punya minimal satu catatan bulan ini.
  const hariSekolah = hariKe >= 0 && hariKe <= 4
  if (hariSekolah && daftar.value.length > 0 && iso < hariIniIso.value) {
    return 'tidak'
  }
  return null
}

interface SelKalender {
  kunci: string
  tanggal: number | null
  status: StatusHari | null
  hariIni: boolean
}

const selKalender = computed<SelKalender[]>(() => {
  const tahun = bulanIni.getFullYear()
  const bulan = bulanIni.getMonth()
  const jumlahHari = new Date(tahun, bulan + 1, 0).getDate()
  // Kolom pertama = Senin (getDay: 0 = Minggu).
  const kosongAwal = (new Date(tahun, bulan, 1).getDay() + 6) % 7

  const sel: SelKalender[] = []
  for (let i = 0; i < kosongAwal; i++) {
    sel.push({ kunci: `kosong-${i}`, tanggal: null, status: null, hariIni: false })
  }
  for (let hari = 1; hari <= jumlahHari; hari++) {
    const tanggal = new Date(tahun, bulan, hari)
    const iso = keIso(tanggal)
    sel.push({
      kunci: iso,
      tanggal: hari,
      status: statusHari(iso, (tanggal.getDay() + 6) % 7),
      hariIni: iso === hariIniIso.value,
    })
  }
  return sel
})

const GAYA_SEL: Record<StatusHari, string> = {
  hadir: 'bg-emerald-500 text-white',
  terlambat: 'bg-amber-400 text-white',
  tidak: 'bg-red-500 text-white',
  lain: 'bg-slate-300 text-slate-700',
}

const LEGENDA: Array<{ label: string; warna: string }> = [
  { label: 'Hadir', warna: 'bg-emerald-500' },
  { label: 'Terlambat', warna: 'bg-amber-400' },
  { label: 'Tidak presensi', warna: 'bg-red-500' },
  { label: 'Izin / Sakit', warna: 'bg-slate-300' },
]
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
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="section-title">Rekap Kehadiran</h2>
            <p class="mt-1 text-xs capitalize text-slate-400">
              {{ labelBulan }} · {{ daftar.length }} catatan
            </p>
          </div>

          <!-- Tombol kalender kehadiran -->
          <button
            type="button"
            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-b from-[#006FBE] to-[#004A80] text-white shadow-md shadow-[#006FBE]/30 transition active:scale-95"
            aria-label="Lihat kalender kehadiran"
            data-testid="buka-kalender"
            @click="kalenderBuka = true"
          >
            <CalendarDays class="size-5" />
          </button>
        </div>

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
          Belum ada riwayat absensi bulan ini.
        </p>
      </section>
    </main>

    <SiswaBottomnav active="riwayat" />

    <!-- ── Kalender kehadiran bulan ini (muncul dari bawah) ── -->
    <div
      v-if="kalenderBuka"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/50"
      role="dialog"
      aria-modal="true"
      aria-label="Kalender kehadiran"
      @click.self="kalenderBuka = false"
    >
      <div class="w-full max-w-md rounded-t-3xl bg-white px-5 pb-8 pt-4 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h3 class="text-base font-bold text-slate-900">Kalender Kehadiran</h3>
            <p class="text-xs font-medium capitalize text-slate-500">{{ labelBulan }}</p>
          </div>
          <button
            type="button"
            class="flex size-9 items-center justify-center rounded-full text-slate-500 transition active:bg-slate-100"
            aria-label="Tutup kalender"
            @click="kalenderBuka = false"
          >
            <X class="size-5" />
          </button>
        </div>

        <!-- Nama hari -->
        <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-semibold text-slate-400">
          <span v-for="hari in NAMA_HARI" :key="hari">{{ hari }}</span>
        </div>

        <!-- Tanggal -->
        <div class="mt-1.5 grid grid-cols-7 gap-1.5">
          <div
            v-for="sel in selKalender"
            :key="sel.kunci"
            class="flex aspect-square items-center justify-center rounded-xl text-sm font-semibold"
            :class="[
              sel.status ? GAYA_SEL[sel.status] : 'text-slate-700',
              sel.hariIni ? 'ring-2 ring-[#006FBE] ring-offset-1' : '',
            ]"
          >
            {{ sel.tanggal }}
          </div>
        </div>

        <!-- Keterangan warna -->
        <ul class="mt-5 grid grid-cols-2 gap-x-4 gap-y-2">
          <li v-for="item in LEGENDA" :key="item.label" class="flex items-center gap-2 text-xs text-slate-600">
            <span class="size-3 rounded-full" :class="item.warna" />
            {{ item.label }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>