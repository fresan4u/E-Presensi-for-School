<script setup lang="ts">
useHead({ title: 'Scan Wajah · Absensi Digital' })

const router = useRouter()
const api = useApi()
const { fetchMe } = useStudentAuth()
const config = useRuntimeConfig()

const {
  videoRef,
  status: faceStatus,
  errorMessage: faceError,
  result: faceResult,
  init: initFace,
  startScan: startFaceScan,
  stopScan: stopFaceScan,
} = useFaceScanner()

const submitting = ref(false)
const notice = ref('')
const showHelp = ref(false)
/** Absen mandiri ditolak server (di luar jendela jam). */
const closed = ref(false)
/** Batas jam dari server, untuk label sesi & pesan. */
const jendela = ref<{ self_cutoff: string; handover_at: string; pulang_from: string } | null>(null)

/** Titik & radius area sekolah; diambil dari backend saat halaman dibuka. */
const schoolLat = ref(Number(config.public.schoolLat))
const schoolLng = ref(Number(config.public.schoolLng))
const radiusMeter = ref(Number(config.public.schoolRadius) || 100)

/**
 * Saklar geofencing. Saat ini dimatikan (default `false`): absensi tidak lagi
 * diblokir oleh lokasi. Nyalakan lewat env NUXT_PUBLIC_GEOFENCE_ENABLED=true.
 */
const geofenceAktif = computed(() => String(config.public.geofenceEnabled) === 'true')

/** State geofencing */
const lokasi = ref<{ lat: number | null; lng: number | null }>({ lat: null, lng: null })
const jarak = ref<number>(0)
const statusLokasi = ref<'loading' | 'valid' | 'invalid' | 'error'>('loading')
const errorMessageLokasi = ref('')

let geoWatchId: number | null = null

/** Rumus Haversine: jarak antar koordinat dalam meter. */
function getDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371000
  const toRad = (deg: number) => (deg * Math.PI) / 180
  const dLat = toRad(lat2 - lat1)
  const dLon = toRad(lon2 - lon1)
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
    Math.sin(dLon / 2) * Math.sin(dLon / 2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return R * c
}

function prosesLokasi(position: GeolocationPosition) {
  const lat = position.coords.latitude
  const lng = position.coords.longitude
  lokasi.value = { lat, lng }

  const jarakHitung = getDistance(lat, lng, schoolLat.value, schoolLng.value)
  jarak.value = Math.round(jarakHitung)

  if (jarak.value <= radiusMeter.value) {
    statusLokasi.value = 'valid'
    errorMessageLokasi.value = ''
  } else {
    statusLokasi.value = 'invalid'
    errorMessageLokasi.value = ''
  }
}

function prosesError() {
  statusLokasi.value = 'error'
  errorMessageLokasi.value = 'Izin GPS ditolak atau tidak tersedia.'
}

/** Hasil scan terakhir (dibaca halaman berhasil). */
const lastScan = useState<ScanResult | null>('last-scan', () => null)

/** Sesi ditentukan server dari jam; label ini hanya untuk tampilan. */
const sesiSaatIni = computed<'masuk' | 'pulang'>(() => {
  if (!jendela.value) return 'masuk'
  const now = new Date()
  const menitSekarang = now.getHours() * 60 + now.getMinutes()
  const menit = (jam: string) => {
    const [h, m] = jam.split(':').map(Number)
    return (h ?? 0) * 60 + (m || 0)
  }
  return menitSekarang >= menit(jendela.value.pulang_from) ? 'pulang' : 'masuk'
})

const labelSesi = computed(() => (sesiSaatIni.value === 'pulang' ? 'Absen Pulang' : 'Absen Masuk'))

/** Kamera hanya boleh menyala jika lokasi valid. */
const bisaScan = computed(() => statusLokasi.value === 'valid' && !closed.value)

// Jangan keluar aplikasi jika tidak ada history (mis. dibuka langsung via URL).
function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/siswa')
}

onMounted(async () => {
  fetchMe().catch(() => {})

  // Batas jam absen dari server (sumber kebenaran) untuk label & pesan.
  try {
    const meta = await api.meta()
    jendela.value = meta.data.attendance_window
  } catch {
    // Abaikan: tombol tetap bisa dicoba, validasi akhir tetap di server.
  }

  if (geofenceAktif.value) {
    // Titik & radius sekolah diambil dari server supaya admin bisa memindahkan
    // area absensi tanpa deploy ulang. Gagal fetch = pakai nilai env/config.
    try {
      const lokasiSekolah = await api.schoolLocation()
      schoolLat.value = lokasiSekolah.data.latitude
      schoolLng.value = lokasiSekolah.data.longitude
      radiusMeter.value = lokasiSekolah.data.radius_meter
    } catch {
      // Abaikan: fallback config tetap dipakai.
    }

    // Geofencing: pantau lokasi siswa secara real-time.
    statusLokasi.value = 'loading'
    if (typeof navigator !== 'undefined' && 'geolocation' in navigator) {
      geoWatchId = navigator.geolocation.watchPosition(
        prosesLokasi,
        prosesError,
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
      )
    } else {
      prosesError()
    }
  } else {
    // Fitur lokasi dimatikan sementara: tidak minta izin GPS dan tidak
    // memblokir absensi. Koordinat tetap dikirim bila nanti diaktifkan lagi.
    statusLokasi.value = 'valid'
    errorMessageLokasi.value = ''
  }

  await initFace()
})

onBeforeUnmount(() => {
  if (geoWatchId !== null && typeof navigator !== 'undefined' && 'geolocation' in navigator) {
    navigator.geolocation.clearWatch(geoWatchId)
    geoWatchId = null
  }
})

/** Setiap descriptor terdeteksi -> langsung dikirim untuk dicocokkan 1:1. */
watch(faceResult, (hasil) => {
  if (hasil && !submitting.value && !closed.value && statusLokasi.value === 'valid') {
    kirimAbsen(hasil.descriptor, hasil.photo)
  }
})

async function kirimAbsen(descriptor: number[], photo?: string) {
  if (statusLokasi.value !== 'valid') {
    notice.value = 'Lokasi tidak valid. Anda harus berada di area sekolah untuk absen.'
    return
  }

  submitting.value = true
  notice.value = ''

  try {
    // Sesi TIDAK dikirim: server menentukan masuk/pulang dari jam.
    // Kirim koordinat GPS untuk geofencing & history.
    const res = await api.studentScan(descriptor, photo, lokasi.value.lat, lokasi.value.lng)
    lastScan.value = res
    stopFaceScan()
    router.push('/siswa/berhasil')
  } catch (error) {
    const kode = (error as { data?: { code?: string } })?.data?.code

    if (kode === 'face_not_recognized') {
      stopFaceScan()
      router.push('/siswa/gagal')
      return
    }

    if (kode === 'attendance_closed') {
      closed.value = true
      stopFaceScan()
      notice.value = apiErrorMessage(error)
      submitting.value = false
      return
    }

    notice.value = apiErrorMessage(error)
    submitting.value = false
    // Ulangi pemindaian setelah gangguan sementara.
    if (statusLokasi.value === 'valid') {
      startFaceScan()
    }
  }
}

function mulaiScan() {
  if (!bisaScan.value) return
  startFaceScan()
}

const statusText = computed(() => {
  switch (faceStatus.value) {
    case 'loading':
      return 'Menyiapkan kamera…'
    case 'error':
      return 'Kamera tidak siap'
    case 'scanning':
      return 'Memindai wajah…'
    case 'detected':
      return 'Wajah terdeteksi — memverifikasi…'
    default:
      return 'Siap memindai'
  }
})
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-white">
    <!-- ── Navbar atas ── -->
    <header
      class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-100 bg-white px-4 py-3"
    >
      <button
        type="button"
        class="-ml-1 flex size-10 items-center justify-center rounded-full text-slate-700 transition active:bg-slate-100"
        aria-label="Kembali"
        @click="goBack"
      >
        <AppIcon name="chevron-left" :size="24" :stroke-width="2" />
      </button>

      <h1 class="text-base font-bold text-slate-900">Scan Wajah</h1>

      <button
        type="button"
        class="-mr-1 flex size-10 items-center justify-center rounded-full text-slate-500 transition active:bg-slate-100"
        aria-label="Bantuan"
        @click="showHelp = true"
      >
        <AppIcon name="help" :size="22" />
      </button>
    </header>

    <!-- ── Banner Geofencing (hanya saat fitur lokasi aktif) ── -->
    <div v-if="geofenceAktif" class="px-5 pt-3">
      <div
        v-if="statusLokasi === 'loading'"
        class="flex items-center justify-center gap-2 rounded-xl bg-amber-50 px-4 py-2.5 text-amber-800"
      >
        <AppIcon name="alert" :size="16" />
        <span class="text-sm font-semibold">📍 Mengambil lokasi GPS...</span>
      </div>
      <div
        v-else-if="statusLokasi === 'valid'"
        class="flex items-center justify-center gap-2 rounded-xl bg-emerald-50 px-4 py-2.5 text-emerald-700"
      >
        <AppIcon name="check-circle" :size="16" />
        <span class="text-sm font-semibold">✅ Lokasi valid. Jarak: {{ jarak }} meter</span>
      </div>
      <div
        v-else-if="statusLokasi === 'invalid'"
        class="flex items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-2.5 text-red-700"
      >
        <AppIcon name="alert" :size="16" />
        <span class="text-center text-sm font-semibold">
          ❌ Anda di luar area sekolah (maks. {{ radiusMeter }} m). Jarak: {{ jarak }} m.
        </span>
      </div>
      <div
        v-else-if="statusLokasi === 'error'"
        class="flex items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-2.5 text-red-700"
      >
        <AppIcon name="alert" :size="16" />
        <span class="text-center text-sm font-semibold">{{ errorMessageLokasi || 'Izin GPS ditolak atau tidak tersedia.' }}</span>
      </div>
    </div>

    <!-- ── Info sesi (ditentukan server dari jam) ── -->
    <div class="px-5 pt-3">
      <div class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5">
        <AppIcon name="clock" :size="16" class="text-brand-600" />
        <span class="text-sm font-semibold text-slate-700">
          {{ closed ? 'Absen ditutup' : labelSesi }}
        </span>
        <span v-if="jendela" class="text-xs text-slate-400">
          · {{ sesiSaatIni === 'pulang' ? 'mulai' : 'sampai' }}
          {{ sesiSaatIni === 'pulang' ? jendela.pulang_from : jendela.self_cutoff }}
        </span>
      </div>
    </div>

    <!-- ── Area kamera / blokir lokasi ── -->
    <div class="mt-4 flex flex-1 flex-col items-center px-5">
      <div
        class="relative flex w-full max-w-sm flex-col items-center rounded-3xl bg-white shadow-xl shadow-slate-900/10 ring-1 ring-slate-100"
      >
        <!-- Blokir total jika lokasi tidak valid -->
        <div
          v-if="statusLokasi !== 'valid'"
          class="flex aspect-square w-full flex-col items-center justify-center rounded-3xl bg-slate-50 px-6 text-center"
        >
          <span class="flex size-16 items-center justify-center rounded-2xl bg-red-50">
            <AppIcon name="alert" :size="32" class="text-red-500" />
          </span>
          <h2 class="mt-4 text-lg font-bold text-slate-900">Lokasi Belum Valid</h2>
          <p class="mt-2 text-sm leading-relaxed text-slate-500">
            <template v-if="statusLokasi === 'loading'">Sedang mengambil lokasi GPS...</template>
            <template v-else-if="statusLokasi === 'invalid'">Anda harus berada di dalam area sekolah (maks. {{ radiusMeter }} meter).</template>
            <template v-else>Aktifkan izin lokasi (GPS) untuk dapat melakukan absensi.</template>
          </p>
        </div>

        <template v-else>
          <div class="relative aspect-square w-full overflow-hidden rounded-3xl">
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="h-full w-full object-cover"
            ></video>

            <!-- Overlay panduan wajah -->
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div
                class="h-[68%] w-[68%] rounded-full border-2 transition-colors"
                :class="{
                  'border-white/70': faceStatus === 'ready',
                  'border-amber-400': faceStatus === 'scanning',
                  'border-emerald-400': faceStatus === 'detected',
                  'border-red-400': faceStatus === 'error',
                }"
              ></div>
            </div>

            <!-- Status overlay -->
            <div
              class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-4 py-1.5 backdrop-blur-sm"
            >
              <p class="text-xs font-medium text-white">{{ statusText }}</p>
            </div>
          </div>

          <div class="w-full px-5 pb-5 pt-4 text-center">
            <p class="text-sm font-semibold text-slate-900">
              {{ faceStatus === 'ready' ? 'Posisikan wajah di dalam lingkaran' : statusText }}
            </p>
            <p v-if="faceError" class="mt-1 text-xs text-red-500">{{ faceError }}</p>
          </div>
        </template>
      </div>

      <!-- ── Notifikasi ── -->
      <p v-if="notice" class="mt-4 text-center text-sm font-medium text-red-600">
        {{ notice }}
      </p>

      <!-- ── Tombol mulai scan ── -->
      <button
        type="button"
        data-testid="mulai-scan"
        class="btn-primary mt-5 mb-6"
        :disabled="faceStatus !== 'ready' || submitting || closed || statusLokasi !== 'valid'"
        @click="mulaiScan"
      >
        <AppIcon name="scan-face" :size="20" />
        <span>{{
          closed
            ? 'Absen Ditutup'
            : statusLokasi !== 'valid'
              ? 'Tidak di Area Sekolah'
              : faceStatus === 'scanning' || submitting
                ? 'Memindai…'
                : 'Mulai Scan'
        }}</span>
      </button>
    </div>

    <!-- ── Modal Bantuan ── -->
    <Teleport to="body">
      <div
        v-if="showHelp"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-5"
        @click="showHelp = false"
      >
        <div
          class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl"
          @click.stop
        >
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Cara Absensi</h2>
            <button
              type="button"
              class="rounded-full p-1 text-slate-400 transition active:bg-slate-100"
              @click="showHelp = false"
            >
              <AppIcon name="chevron-left" :size="20" />
            </button>
          </div>
          <ol class="mt-4 space-y-3 text-sm text-slate-600">
            <template v-if="geofenceAktif">
              <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">1</span>
                <span>Pastikan kamu berada di dalam area sekolah (maksimal jarak {{ radiusMeter }} meter).</span>
              </li>
              <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">2</span>
                <span>Izinkan akses lokasi GPS saat diminta.</span>
              </li>
            </template>
            <li class="flex gap-3">
              <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ geofenceAktif ? 3 : 1 }}</span>
              <span>Tekan <span class="font-semibold text-slate-900">Mulai Scan</span>, posisikan wajah di dalam lingkaran hingga terverifikasi otomatis.</span>
            </li>
          </ol>
        </div>
      </div>
    </Teleport>
  </div>
</template>
