<template>
  <div class="flex min-h-screen flex-col bg-gray-50">
    <!-- ===================== HEADER ===================== -->
    <header class="flex flex-col gap-4 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <div :class="[ICON_TILE, 'h-10 w-10']">
          <ScanFace class="h-5 w-5 drop-shadow" />
        </div>
        <div class="flex flex-col">
          <span class="text-base font-bold leading-tight text-slate-800">Mesin Absensi Operasional</span>
          <span class="text-xs leading-tight text-gray-500">
            Pindai wajah siswa untuk mencatat kehadiran
          </span>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-4 sm:justify-end">
        <!-- Keluar dari mode kiosk (dengan konfirmasi) -->
        <button
          type="button"
          class="flex items-center gap-1.5 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
          @click="requestLeave()"
        >
          <LogOut class="h-4 w-4" />
          Keluar
        </button>
      </div>
    </header>

    <!-- ===================== KONTEN UTAMA ===================== -->
    <section class="grid flex-1 gap-6 p-6 lg:grid-cols-3">
      <!-- Kolom kiri: kamera pemindai + hasil -->
      <div class="flex flex-col gap-4 lg:col-span-2">
        <!-- Toolbar pemindai -->
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center overflow-hidden rounded-md border border-gray-200 bg-white">
            <button
              v-for="modeOpt in modeOptions"
              :key="modeOpt.value"
              type="button"
              class="px-3 py-2 text-sm font-medium transition-colors"
              :class="[
                modeOpt.value !== 'face' ? 'border-l border-gray-200' : '',
                mode === modeOpt.value
                  ? BTN_GRADIENT
                  : 'bg-white text-gray-600 hover:bg-slate-50',
              ]"
              @click="switchMode(modeOpt.value)"
            >
              <span class="flex items-center gap-1.5">
                <component :is="modeOpt.icon" class="h-4 w-4" />
                {{ modeOpt.label }}
              </span>
            </button>
          </div>

          <base-button
            v-if="mode === 'face'"
            :variant="scanning ? 'outline' : 'solid'"
            :class="scanning ? '' : BTN_GRADIENT"
            :data-testid="scanning ? 'hentikan-scan' : 'mulai-scan'"
            :disabled="faceStatus === 'loading'"
            @click="scanning ? stopScanning() : startScanning()"
          >
            <component :is="scanning ? X : ScanFace" class="h-4 w-4" />
            {{ scanning ? 'Hentikan Pemindaian' : 'Mulai Pemindaian' }}
          </base-button>
        </div>

        <!-- Kotak kamera / pemindai -->
        <div class="relative aspect-video overflow-hidden rounded-2xl bg-gray-900">
          <template v-if="mode === 'face'">
            <!-- Feed kamera -->
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="h-full w-full -scale-x-100 object-cover"
            />

            <!-- Overlay garis pembidik wajah: lingkaran hijau -->
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div
                class="h-64 w-64 rounded-full border-2 border-dashed transition-colors"
                :class="guideClass"
              />
            </div>

            <!-- Chip status di atas -->
            <div
              class="absolute left-1/2 top-4 flex -translate-x-1/2 items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium"
              :class="statusChip.class"
            >
              <component
                :is="statusChip.icon"
                class="h-3.5 w-3.5"
                :class="statusChip.spin ? 'animate-spin' : ''"
              />
              {{ statusChip.label }}
            </div>

            <!-- Overlay loading kamera & model -->
            <div
              v-if="faceStatus === 'loading'"
              class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/75 text-white"
            >
              <Loader2 class="h-7 w-7 animate-spin text-sky-300" />
              <p class="text-sm text-gray-200">Menyiapkan kamera &amp; model...</p>
            </div>

            <!-- Overlay: izin kamera DIBLOKIR (browser tidak bisa meminta lagi) -->
            <div
              v-else-if="faceStatus === 'error' && cameraPermission === 'denied'"
              class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 overflow-y-auto bg-black/85 px-6 py-4 text-center text-white"
              data-testid="izin-kamera-diblokir"
            >
              <CameraOff class="h-9 w-9 text-sky-300" />
              <h3 class="text-base font-semibold">Izin kamera diblokir</h3>
              <p class="max-w-md text-xs text-gray-300">
                Browser mengingat pilihan "Blokir" dan tidak akan menampilkan permintaan izin lagi
                saat halaman dimuat ulang. Izinkan kamera secara manual:
              </p>
              <ol class="max-w-md list-decimal space-y-1 pl-5 text-left text-xs text-gray-200">
                <li>Klik ikon pengaturan (gembok) di sebelah kiri alamat halaman ini.</li>
                <li>Ubah <span class="font-semibold">Kamera</span> menjadi <span class="font-semibold">Izinkan</span>.</li>
                <li>Kembali ke halaman ini. Pemindaian dimulai otomatis, atau klik Coba Lagi.</li>
              </ol>
              <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                <base-button variant="solid" :class="BTN_GRADIENT" @click="retryCamera">
                  <RotateCcw class="h-4 w-4" />
                  Coba Lagi
                </base-button>
                <base-button variant="outline" @click="reloadPage">
                  Muat Ulang Halaman
                </base-button>
              </div>
            </div>

            <!-- Overlay error kamera & model (selain izin) -->
            <div
              v-else-if="faceStatus === 'error'"
              class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/85 px-6 text-center text-white"
            >
              <CameraOff class="h-9 w-9 text-gray-300" />
              <p class="max-w-sm text-sm text-gray-200">{{ faceError }}</p>
              <base-button variant="solid" :class="BTN_GRADIENT" @click="retryCamera">
                <RotateCcw class="h-4 w-4" />
                Coba Lagi
              </base-button>
            </div>
          </template>

          <!-- Mode fingerprint -->
          <div
            v-else
            class="absolute inset-0 flex flex-col items-center justify-center gap-4 px-6 text-center text-white"
          >
            <Fingerprint class="h-24 w-24 animate-pulse text-sky-300" />
            <div>
              <h3 class="text-base font-semibold">Tempelkan sidik jari pada perangkat</h3>
              <p class="mt-1 text-sm text-gray-300">
                Sistem akan mendeteksi dan memverifikasi data sidik jari secara otomatis.
              </p>
            </div>
            <div
              class="flex items-center gap-2 rounded-md border border-white/20 bg-white/5 px-4 py-2 text-sm text-sky-200"
            >
              <span class="h-2 w-2 animate-pulse rounded-full bg-sky-300" />
              Menunggu sidik jari...
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
              <base-button variant="outline" @click="resetScan">
                <X class="h-4 w-4" />
                Batal
              </base-button>
              <base-button variant="outline" @click="switchMode('face')">
                <ScanFace class="h-4 w-4" />
                Gunakan Pindai Wajah
              </base-button>
            </div>
          </div>
        </div>

        <!-- Hasil absensi terakhir -->
        <div
          v-if="result"
          class="rounded-2xl border bg-white p-5 shadow-sm"
          :class="result.ok ? 'border-sky-200' : 'border-amber-200'"
        >
          <div
            class="flex items-center gap-3 rounded-md px-4 py-3"
            :class="result.ok ? 'border border-sky-200 bg-sky-50' : 'border border-amber-200 bg-amber-50'"
          >
            <CheckCircle2 class="h-5 w-5 shrink-0" :class="result.ok ? 'text-[#006FBE]' : 'text-amber-600'" />
            <div>
              <p class="text-sm font-semibold" :class="result.ok ? 'text-[#004A80]' : 'text-amber-800'">
                {{ result.banner }}
              </p>
              <p class="text-xs" :class="result.ok ? 'text-[#006FBE]' : 'text-amber-700'">{{ result.jam }}</p>
            </div>
          </div>

          <div class="mt-5 flex items-center gap-4">
            <div :class="[AVATAR_TILE, 'h-16 w-16 rounded-lg text-xl']">
              {{ initial(result.name) }}
            </div>
            <div class="min-w-0">
              <p class="truncate text-base font-semibold text-slate-800">{{ result.name }}</p>
              <p class="text-sm text-gray-500">{{ result.kelas }}</p>
            </div>
          </div>

          <dl class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-5 text-sm">
            <div class="flex items-center justify-between gap-4">
              <dt class="text-gray-500">NIS</dt>
              <dd class="font-medium text-slate-800">{{ result.nis }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-gray-500">Tanggal</dt>
              <dd class="font-medium text-slate-800">{{ result.tanggal }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-gray-500">Metode</dt>
              <dd class="font-medium text-slate-800">{{ result.metode }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-gray-500">Status</dt>
              <dd>
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="STATUS_META[result.status].badge"
                >
                  {{ STATUS_META[result.status].label }}
                </span>
              </dd>
            </div>
          </dl>

          <div
            v-if="result.ok && result.waQueued"
            class="mt-5 flex items-center gap-2 rounded-md border border-sky-200 bg-sky-50/60 px-4 py-3 text-xs font-medium text-[#004A80]"
          >
            <MessageCircle class="h-4 w-4 shrink-0" />
            Notifikasi WhatsApp (foto + detail absensi) dikirim ke wali
          </div>
          <div
            v-else-if="result.ok"
            class="mt-5 flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-medium text-slate-600"
          >
            <MessageCircle class="h-4 w-4 shrink-0" />
            Notifikasi WhatsApp tidak dikirim — kontak wali ber-consent belum tersedia atau fitur dinonaktifkan
          </div>
        </div>

        <!-- Pesan error scan -->
        <p
          v-if="scanError"
          class="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          role="alert"
        >
          <TriangleAlert class="h-4 w-4 shrink-0" />
          {{ scanError }}
        </p>
      </div>

      <!-- Kolom kanan: live log absensi -->
      <div class="flex h-full flex-col rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-800">Riwayat Scan Terbaru</h2>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
            {{ logs.length }}
          </span>
        </div>
        <p class="mt-1 text-xs text-gray-500">
          Sesi {{ session === 'masuk' ? 'absensi masuk' : 'absensi pulang' }} · hari ini
        </p>

        <ul class="mt-4 flex flex-1 flex-col gap-2 overflow-y-auto pr-1">
          <li
            v-for="item in logs"
            :key="item.id"
            class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2.5"
          >
            <span :class="[AVATAR_TILE, 'h-9 w-9 rounded-full text-sm']">
              {{ initial(item.name) }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-800">{{ item.name }}</p>
              <p class="truncate text-xs text-gray-500">{{ item.kelas }} · {{ item.nis }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-end">
              <span class="text-xs font-medium tabular-nums text-slate-700">{{ item.time }}</span>
              <span
                class="text-xs font-medium"
                :class="item.created ? 'text-[#006FBE]' : 'text-amber-600'"
              >
                {{ item.created ? 'Berhasil' : 'Sudah tercatat' }}
              </span>
            </div>
          </li>

          <li
            v-if="!logs.length"
            class="flex flex-1 items-center justify-center px-4 py-12 text-center text-sm text-gray-500"
          >
            Belum ada scan pada sesi ini.
          </li>
        </ul>
      </div>
    </section>

    <!-- Konfirmasi keluar mode kiosk -->
    <modal
      v-model="leaveModalOpen"
      title="Keluar dari mesin absensi?"
      description="Pemindaian akan dihentikan. Yakin ingin keluar dari mode kiosk?"
    >
      <template #footer>
        <base-button variant="outline" @click="cancelLeave">Batal</base-button>
        <base-button variant="solid" :class="BTN_GRADIENT" @click="confirmLeave">Keluar</base-button>
      </template>
    </modal>
  </div>
</template>

<script setup lang="ts">
import {
  CameraOff,
  CheckCircle2,
  Fingerprint,
  Loader2,
  LogOut,
  MessageCircle,
  RotateCcw,
  ScanFace,
  TriangleAlert,
  X,
} from '@lucide/vue'
import type {
  ApiAttendance,
  ApiAttendanceWindow,
  ScanResult as ApiScanResult,
} from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: false,
})

/* ------------------------------------------------------------------ */
/* Gaya                                                                */
/* ------------------------------------------------------------------ */

const BLUE_BG = 'bg-gradient-to-b from-[#006FBE] to-[#004A80]'

// Tombol utama & tombol mode aktif: gradient biru + bayangan berwarna
const BTN_GRADIENT = `${BLUE_BG} text-white shadow-md shadow-[#006FBE]/30 hover:brightness-110`

// Kotak ikon header
const ICON_TILE = `flex shrink-0 items-center justify-center rounded-lg text-white shadow-md shadow-[#006FBE]/40 ring-1 ring-white/20 ${BLUE_BG}`

// Avatar inisial siswa (hasil scan & riwayat)
const AVATAR_TILE = `flex shrink-0 items-center justify-center font-semibold text-white shadow-sm ring-1 ring-white/20 ${BLUE_BG}`

type Session = 'masuk' | 'pulang'
type ScanMode = 'face' | 'fingerprint'
type CameraPermission = 'granted' | 'denied' | 'prompt' | 'unknown'

interface LogItem {
  id: number
  name: string
  nis: string
  kelas: string
  time: string
  status: AttendanceStatus
  created: boolean
}

interface ScanResultView {
  name: string
  nis: string
  kelas: string
  tanggal: string
  jam: string
  metode: string
  status: AttendanceStatus
  banner: string
  ok: boolean
  /** Benar-benar masuk antrean notifikasi WhatsApp ke wali? */
  waQueued: boolean
}

const api = useApi()

/** Konfirmasi sebelum keluar dari mode kiosk. */
const { leaveModalOpen, requestLeave, confirmLeave, cancelLeave } = useLeaveGuard()

const modeOptions = [
  { value: 'face', label: 'Wajah', icon: ScanFace },
  { value: 'fingerprint', label: 'Fingerprint', icon: Fingerprint },
] as const

const {
  videoRef,
  status: faceStatus,
  errorMessage: faceError,
  result: faceResult,
  init: initFace,
  startScan,
  stopScan,
  stopCamera: stopFaceCamera,
} = useFaceScanner()

const mode = ref<ScanMode>('face')
/** Sesi mengikuti jam server secara otomatis (tidak bisa di-override manual). */
const session = ref<Session>('masuk')
const logs = ref<LogItem[]>([])
const result = ref<ScanResultView | null>(null)
const scanError = ref('')
const fingerprintScanning = ref(false)
const processing = ref(false)
const autoScan = ref(true)
/** Jendela absen dari server (batas jam, jadwal hari ini, status buka/tutup). */
const attendanceWindow = ref<ApiAttendanceWindow | null>(null)
const now = ref(new Date())

let clockTimer: ReturnType<typeof setInterval> | null = null
let resumeTimer: ReturnType<typeof setTimeout> | null = null
let fingerprintTimer: ReturnType<typeof setTimeout> | null = null

/* ---------- Izin kamera ---------- */
/** Status izin kamera dari browser: granted / denied / prompt / unknown. */
const cameraPermission = ref<CameraPermission>('unknown')

let permissionStatus: PermissionStatus | null = null
let retrying = false

/**
 * Nyalakan ulang kamera. Bila izin masih "prompt", browser menampilkan dialog
 * izin; bila "denied", browser tidak akan menampilkannya lagi (harus diubah
 * manual lewat ikon gembok), jadi panel petunjuk tetap tampil.
 */
const retryCamera = async () => {
  if (retrying || mode.value !== 'face') return

  retrying = true
  try {
    scanError.value = ''
    await initFace()
    if (faceStatus.value === 'ready') {
      autoScan.value = true
      startScan()
    }
  } finally {
    retrying = false
  }
}

const handlePermissionChange = () => {
  if (!permissionStatus) return

  cameraPermission.value = permissionStatus.state as CameraPermission

  // Izin baru saja diubah (diizinkan atau direset): coba nyalakan kamera lagi.
  if (permissionStatus.state !== 'denied') retryCamera()
}

/** Pantau izin kamera supaya halaman bereaksi saat izin diubah petugas. */
const watchCameraPermission = async () => {
  try {
    const status = await navigator.permissions.query({ name: 'camera' as PermissionName })
    permissionStatus = status
    cameraPermission.value = status.state as CameraPermission
    status.onchange = handlePermissionChange
  } catch {
    // Browser tidak mendukung query izin kamera; andalkan tombol Coba Lagi.
    cameraPermission.value = 'unknown'
  }
}

/** Petugas kembali ke jendela ini (mis. setelah mengubah izin): coba lagi. */
const handleWindowFocus = () => {
  if (mode.value !== 'face' || faceStatus.value !== 'error') return

  if (permissionStatus) cameraPermission.value = permissionStatus.state as CameraPermission
  if (cameraPermission.value !== 'denied') retryCamera()
}

const reloadPage = () => {
  globalThis.location.reload()
}

/* ---------- Format ---------- */
const toIsoDate = (date: Date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

const clockFormat = new Intl.DateTimeFormat('id-ID', {
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hour12: false,
})

const longDateFormat = new Intl.DateTimeFormat('id-ID', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

/** Menit sejak tengah malam dari "HH:MM". */
const keMenit = (jam: string) => {
  const [h, m] = jam.split(':').map(Number)
  return (h ?? 0) * 60 + (m || 0)
}

/** Sesi menurut jam server (dipakai saat override manual tidak aktif). */
const sesiOtotomatis = computed<Session>(() => {
  const menitSekarang = now.value.getHours() * 60 + now.value.getMinutes()
  const pulangFrom = attendanceWindow.value?.pulang_from
    ? keMenit(attendanceWindow.value.pulang_from)
    : 15 * 60
  return menitSekarang >= pulangFrom ? 'pulang' : 'masuk'
})

/** Sinkronkan sesi dari jam selama petugas belum mengaktifkan override. */
watch(
  sesiOtotomatis,
  (next) => {
    if (next === session.value) return
    session.value = next
    loadHistory()
  },
)

const initial = (name: string) => name.charAt(0).toUpperCase()

/** Sedang memindai: mode wajah pakai status face-api, fingerprint pakai simulasi. */
const scanning = computed(() =>
  mode.value === 'face' ? faceStatus.value === 'scanning' : fingerprintScanning.value,
)

/* ---------- Chip & pembidik ---------- */
const statusChip = computed(() => {
  switch (faceStatus.value) {
    case 'scanning':
      return { label: 'Memindai wajah', icon: Loader2, class: 'border-sky-200 bg-sky-50 text-[#004A80]', spin: true }
    case 'detected':
      return { label: 'Wajah terdeteksi', icon: CheckCircle2, class: 'border-sky-200 bg-sky-50 text-[#004A80]', spin: false }
    case 'error':
      return {
        label: cameraPermission.value === 'denied' ? 'Izin kamera ditolak' : 'Kamera bermasalah',
        icon: TriangleAlert,
        class: 'border-red-200 bg-red-50 text-red-700',
        spin: false,
      }
    default:
      return { label: 'Menyiapkan...', icon: Loader2, class: 'border-gray-200 bg-gray-50 text-gray-600', spin: true }
  }
})

/** Warna lingkaran pembidik wajah: hijau di semua kondisi. */
const guideClass = computed(() => {
  if (faceStatus.value === 'detected') return 'border-emerald-500'
  if (faceStatus.value === 'scanning') return 'animate-pulse border-emerald-400'
  return 'border-emerald-400/60'
})

/* ---------- Muat data ---------- */
const toLogItem = (attendance: ApiAttendance): LogItem => ({
  id: attendance.id,
  name: attendance.student?.full_name ?? '-',
  nis: attendance.student?.nis ?? '-',
  kelas: attendance.student?.classroom?.name ?? '-',
  time: clockFormat.format(new Date(attendance.checked_at)),
  status: attendance.status.code,
  created: true,
})

/** Ubah respons scan backend jadi bentuk panel hasil. */
const mapScan = (res: ApiScanResult): ScanResultView => {
  const attendance = res.data
  const methodKey = attendance.method.code.replace('_', ' ') as AttendanceMethod

  return {
    name: attendance.student?.full_name ?? '-',
    nis: attendance.student?.nis ?? '-',
    kelas: attendance.student?.classroom?.name ?? '-',
    tanggal: longDateFormat.format(new Date(`${attendance.attendance_date}T00:00:00`)),
    jam: clockFormat.format(new Date(attendance.checked_at)),
    metode: METHOD_LABEL[methodKey] ?? attendance.method.label,
    status: attendance.status.code,
    banner: res.meta.created
      ? session.value === 'masuk'
        ? 'Absen Masuk berhasil'
        : 'Absen Pulang berhasil'
      : 'Sudah tercatat sebelumnya',
    ok: res.meta.created,
    waQueued: res.meta.wa_queued,
  }
}

const loadBoundary = async () => {
  try {
    const res = await api.meta()
    attendanceWindow.value = res.data.attendance_window
    // Set sesi awal dari jam server.
    session.value = sesiOtotomatis.value
  } catch {
    // Info batas absen opsional; abaikan bila gagal.
  }
}

const loadHistory = async () => {
  try {
    const res = await api.attendances({
      date: toIsoDate(new Date()),
      session: session.value,
      per_page: 50,
    })
    logs.value = res.data.map(toLogItem)
  } catch (error) {
    scanError.value = apiErrorMessage(error)
  }
}

/* ---------- Pemindaian berkelanjutan ---------- */
const scheduleResume = () => {
  if (resumeTimer) clearTimeout(resumeTimer)
  resumeTimer = setTimeout(() => {
    if (autoScan.value && mode.value === 'face') startScan()
  }, 1500)
}

watch(faceResult, async (detected) => {
  if (!detected || processing.value || mode.value !== 'face') return

  processing.value = true
  try {
    const res = await api.scan(detected.descriptor, session.value, detected.photo)
    result.value = mapScan(res)

    // Server yang menentukan apakah absensi baru dibuat atau sudah ada.
    const item = { ...toLogItem(res.data), created: res.meta.created }
    logs.value = [item, ...logs.value.filter((log) => log.id !== item.id)].slice(0, 50)

    scanError.value = res.meta.created
      ? ''
      : 'Siswa ini sudah absen pada sesi ini. Absensi sebelumnya yang ditampilkan.'
  } catch (error) {
    result.value = null
    if ((error as { data?: { code?: string } })?.data?.code === 'face_not_recognized') {
      scanError.value = 'Wajah tidak dikenali. Pastikan wajah sudah terdaftar, lalu coba lagi.'
    } else {
      scanError.value = apiErrorMessage(error)
    }
  } finally {
    processing.value = false
    scheduleResume()
  }
})

/* ---------- Aksi pemindai ---------- */
const startScanning = async () => {
  scanError.value = ''

  if (mode.value === 'fingerprint') {
    startFingerprint()
    return
  }

  if (faceStatus.value === 'error') {
    await initFace()
    if (faceStatus.value === 'error') return
  }

  result.value = null
  autoScan.value = true
  startScan()
}

const stopScanning = () => {
  autoScan.value = false
  if (mode.value === 'face') {
    stopScan()
  } else {
    stopFingerprint()
  }
}

/** Batalkan pemindaian & bersihkan panel hasil. */
const resetScan = () => {
  stopScanning()
  result.value = null
  scanError.value = ''
}

const startFingerprint = () => {
  stopFingerprint()
  fingerprintScanning.value = true
  fingerprintTimer = setTimeout(() => {
    fingerprintScanning.value = false
    scanError.value = 'Metode fingerprint belum tersedia pada sistem ini. Gunakan Face Recognition.'
  }, 1200)
}

const stopFingerprint = () => {
  if (fingerprintTimer) clearTimeout(fingerprintTimer)
  fingerprintTimer = null
  fingerprintScanning.value = false
}

const switchMode = async (next: ScanMode) => {
  if (next === mode.value) return

  stopFingerprint()
  if (resumeTimer) clearTimeout(resumeTimer)
  scanError.value = ''
  result.value = null
  mode.value = next

  if (next === 'fingerprint') {
    // Bebaskan kamera saat masuk mode sidik jari.
    stopFaceCamera()
    autoScan.value = false
    startFingerprint()
  } else {
    // Balik ke wajah: tunggu <video> dirender ulang, lalu nyalakan kamera.
    autoScan.value = true
    await nextTick()
    await initFace()
    if (faceStatus.value === 'ready') startScan()
  }
}

/* ---------- Lifecycle ---------- */
onMounted(async () => {
  clockTimer = setInterval(() => {
    now.value = new Date()
  }, 1000)

  globalThis.addEventListener('focus', handleWindowFocus)

  await Promise.all([loadBoundary(), loadHistory()])

  // Pantau izin kamera lebih dulu agar perubahan izin langsung terdeteksi.
  await watchCameraPermission()

  // Kamera + model otomatis menyala. Bila izin masih "prompt", dialog izin
  // browser muncul di sini; bila "denied", panel petunjuk yang tampil.
  await initFace()
  if (faceStatus.value === 'ready') startScan()
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
  if (resumeTimer) clearTimeout(resumeTimer)
  globalThis.removeEventListener('focus', handleWindowFocus)
  if (permissionStatus) permissionStatus.onchange = null
  stopFingerprint()
  stopScan()
})
</script>