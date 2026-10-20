<template>
  <div class="flex min-h-screen flex-col bg-gray-50">
    <!-- ===================== HEADER ===================== -->
    <header class="flex flex-col gap-4 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-white">
          <ScanFace class="h-5 w-5" />
        </div>
        <div class="flex flex-col">
          <span class="text-base font-bold leading-tight text-slate-800">Mesin Absensi Operasional</span>
          <span class="text-xs leading-tight text-gray-500">
            Pindai wajah siswa untuk mencatat kehadiran
          </span>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-4 sm:justify-end">
        <div class="flex flex-col gap-1">
          <!-- Toggle sesi: masuk / pulang -->
          <div class="flex items-center overflow-hidden rounded-md border border-gray-200 bg-white">
            <button
              v-for="opt in sessionOptions"
              :key="opt.value"
              type="button"
              class="px-4 py-2 text-sm font-medium transition-colors"
              :class="[
                opt.value !== 'masuk' ? 'border-l border-gray-200' : '',
                session === opt.value
                  ? 'bg-emerald-900 text-white'
                  : 'bg-white text-gray-600 hover:bg-slate-50',
              ]"
              @click="switchSession(opt.value)"
            >
              {{ opt.label }}
            </button>
          </div>
          <p class="flex items-center gap-1.5 text-xs font-medium text-red-600">
            <Clock class="h-3.5 w-3.5" />
            Batas absen {{ session === 'masuk' ? 'masuk' : 'pulang' }} {{ boundaryTime }}
          </p>
        </div>

        <!-- Jam digital + tanggal -->
        <div class="flex flex-col items-end">
          <span class="text-2xl font-bold tabular-nums tracking-tight text-slate-800 sm:text-3xl">
            {{ clockTime }}
            <span class="text-sm font-semibold text-gray-500">WIB</span>
          </span>
          <span class="text-xs text-gray-500">{{ todayLabel }}</span>
        </div>

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
                  ? 'bg-emerald-900 text-white'
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

            <!-- Overlay garis pembidik wajah -->
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div
                class="h-64 w-48 rounded-[50%] border-2 border-dashed transition-colors"
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
              <Loader2 class="h-7 w-7 animate-spin text-emerald-400" />
              <p class="text-sm text-gray-200">Menyiapkan kamera &amp; model...</p>
            </div>

            <!-- Overlay error kamera & model -->
            <div
              v-else-if="faceStatus === 'error'"
              class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/85 px-6 text-center text-white"
            >
              <CameraOff class="h-9 w-9 text-gray-300" />
              <p class="max-w-sm text-sm text-gray-200">{{ faceError }}</p>
              <base-button variant="solid" @click="startScanning">
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
            <Fingerprint class="h-24 w-24 animate-pulse text-emerald-400" />
            <div>
              <h3 class="text-base font-semibold">Tempelkan sidik jari pada perangkat</h3>
              <p class="mt-1 text-sm text-gray-300">
                Sistem akan mendeteksi dan memverifikasi data sidik jari secara otomatis.
              </p>
            </div>
            <div
              class="flex items-center gap-2 rounded-md border border-white/20 bg-white/5 px-4 py-2 text-sm text-emerald-300"
            >
              <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400" />
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
          :class="result.ok ? 'border-emerald-200' : 'border-amber-200'"
        >
          <div
            class="flex items-center gap-3 rounded-md px-4 py-3"
            :class="result.ok ? 'border border-emerald-200 bg-emerald-50' : 'border border-amber-200 bg-amber-50'"
          >
            <CheckCircle2 class="h-5 w-5 shrink-0" :class="result.ok ? 'text-emerald-700' : 'text-amber-600'" />
            <div>
              <p class="text-sm font-semibold" :class="result.ok ? 'text-emerald-800' : 'text-amber-800'">
                {{ result.banner }}
              </p>
              <p class="text-xs" :class="result.ok ? 'text-emerald-700' : 'text-amber-700'">{{ result.jam }}</p>
            </div>
          </div>

          <div class="mt-5 flex items-center gap-4">
            <div
              class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-xl font-semibold text-white"
            >
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
            class="mt-5 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-xs font-medium text-emerald-800"
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
            <span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-sm font-semibold text-white"
            >
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
                :class="item.created ? 'text-emerald-600' : 'text-amber-600'"
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
        <base-button variant="solid" @click="confirmLeave">Keluar</base-button>
      </template>
    </modal>
  </div>
</template>

<script setup lang="ts">
import {
  CameraOff,
  CheckCircle2,
  Clock,
  Fingerprint,
  Loader2,
  LogOut,
  MessageCircle,
  RotateCcw,
  ScanFace,
  TriangleAlert,
  X,
} from '@lucide/vue'
import type { ApiAttendance, ScanResult as ApiScanResult } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: false,
})

type Session = 'masuk' | 'pulang'
type ScanMode = 'face' | 'fingerprint'

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

const sessionOptions = [
  { value: 'masuk', label: 'Masuk' },
  { value: 'pulang', label: 'Pulang' },
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
const session = ref<Session>('masuk')
const logs = ref<LogItem[]>([])
const result = ref<ScanResultView | null>(null)
const scanError = ref('')
const fingerprintScanning = ref(false)
const processing = ref(false)
const autoScan = ref(true)
const boundaryMap = ref<Record<Session, string>>({ masuk: '--:--', pulang: '--:--' })
const now = ref(new Date())

let clockTimer: ReturnType<typeof setInterval> | null = null
let resumeTimer: ReturnType<typeof setTimeout> | null = null
let fingerprintTimer: ReturnType<typeof setTimeout> | null = null

/* ---------- Format ---------- */
const toIsoDate = (date: Date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

const clockFormat = new Intl.DateTimeFormat('id-ID', {
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hour12: false,
})

const dateFormat = new Intl.DateTimeFormat('id-ID', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

const longDateFormat = new Intl.DateTimeFormat('id-ID', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

const clockTime = computed(() => clockFormat.format(now.value))
const todayLabel = computed(() => dateFormat.format(now.value))
const boundaryTime = computed(() => boundaryMap.value[session.value])

const initial = (name: string) => name.charAt(0).toUpperCase()

/** Sedang memindai: mode wajah pakai status face-api, fingerprint pakai simulasi. */
const scanning = computed(() =>
  mode.value === 'face' ? faceStatus.value === 'scanning' : fingerprintScanning.value,
)

/* ---------- Chip & pembidik ---------- */
const statusChip = computed(() => {
  switch (faceStatus.value) {
    case 'scanning':
      return { label: 'Memindai wajah', icon: Loader2, class: 'border-emerald-200 bg-emerald-50 text-emerald-800', spin: true }
    case 'detected':
      return { label: 'Wajah terdeteksi', icon: CheckCircle2, class: 'border-emerald-200 bg-emerald-50 text-emerald-800', spin: false }
    case 'error':
      return { label: 'Kamera bermasalah', icon: TriangleAlert, class: 'border-red-200 bg-red-50 text-red-700', spin: false }
    default:
      return { label: 'Menyiapkan...', icon: Loader2, class: 'border-gray-200 bg-gray-50 text-gray-600', spin: true }
  }
})

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
    const map: Record<Session, string> = { masuk: '--:--', pulang: '--:--' }
    for (const item of res.data.attendance_sessions) {
      if ((item.code === 'masuk' || item.code === 'pulang') && item.boundary_time) {
        map[item.code] = item.boundary_time.slice(0, 5)
      }
    }
    boundaryMap.value = map
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

    const item = toLogItem(res.data)
    logs.value = [item, ...logs.value.filter((log) => log.id !== item.id)].slice(0, 50)
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

const switchSession = async (next: Session) => {
  if (next === session.value) return
  session.value = next
  scanError.value = ''
  result.value = null
  await loadHistory()
}

/* ---------- Lifecycle ---------- */
onMounted(async () => {
  clockTimer = setInterval(() => {
    now.value = new Date()
  }, 1000)

  await Promise.all([loadBoundary(), loadHistory()])

  // Kamera + model otomatis menyala, lalu langsung memindai.
  await initFace()
  if (faceStatus.value === 'ready') startScan()
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
  if (resumeTimer) clearTimeout(resumeTimer)
  stopFingerprint()
  stopScan()
})
</script>
