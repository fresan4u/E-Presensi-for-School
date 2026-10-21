<template>
  <div class="flex min-h-screen flex-col bg-slate-50">
    <!-- Header Kiosk -->
    <header
      class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4"
    >
      <div class="flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-900">
          <ShieldCheck class="h-5 w-5 text-white" />
        </div>
        <div class="flex flex-col">
          <span class="text-sm font-bold leading-tight text-slate-800">Presensi</span>
          <span class="text-xs leading-tight text-gray-500">Pemindai Wajah</span>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <!-- Sesi absen: otomatis dari jam, bisa di-override petugas -->
        <div class="flex items-center gap-2">
          <div class="flex items-center overflow-hidden rounded-md border border-gray-200 bg-white">
            <button
              v-for="opt in sessionOptions"
              :key="opt.value"
              type="button"
              class="px-3 py-1.5 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
              :class="[
                opt.value !== 'masuk' ? 'border-l border-gray-200' : '',
                session === opt.value
                  ? 'bg-emerald-900 text-white'
                  : 'bg-white text-gray-600 hover:bg-slate-50',
              ]"
              :disabled="!manualSession || pageStatus === 'scanning' || pageStatus === 'submitting'"
              @click="pilihSesi(opt.value)"
            >
              {{ opt.label }}
            </button>
          </div>
          <button
            type="button"
            class="rounded-md border px-2.5 py-1.5 text-[11px] font-semibold transition-colors"
            :class="manualSession
              ? 'border-amber-300 bg-amber-50 text-amber-800'
              : 'border-gray-200 bg-white text-gray-500 hover:bg-slate-50'"
            :title="manualSession ? 'Kembali ke sesi otomatis' : 'Pilih sesi manual (pulang cepat/sakit)'"
            @click="manualSession ? kembaliOtomatis() : (manualSession = true)"
          >
            {{ manualSession ? 'Manual' : 'Otomatis' }}
          </button>
        </div>

        <!-- Status umum -->
        <span
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium"
          :class="statusChip.class"
        >
          <component
            :is="statusChip.icon"
            class="h-3.5 w-3.5"
            :class="statusChip.spin ? 'animate-spin' : ''"
          />
          {{ statusChip.label }}
        </span>

        <!-- Keluar dari mode kiosk (dengan konfirmasi) -->
        <button
          type="button"
          class="flex items-center gap-1.5 rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
          @click="requestLeave()"
        >
          <LogOut class="h-3.5 w-3.5" />
          Keluar
        </button>
      </div>
    </header>

    <!-- Konten Utama -->
    <main class="flex flex-1 items-center justify-center p-6">
      <div class="w-full max-w-2xl">
        <!-- Area Video Kamera -->
        <div
          class="relative aspect-video w-full overflow-hidden rounded-xl border border-gray-200 bg-black shadow-sm"
        >
          <video
            ref="videoRef"
            autoplay
            playsinline
            muted
            class="h-full w-full -scale-x-100 object-cover"
          />

          <!-- Sudut pembidik saat memindai -->
          <template v-if="pageStatus === 'scanning' || pageStatus === 'submitting' || pageStatus === 'detected'">
            <div
              class="absolute left-1/2 top-1/2 h-40 w-32 -translate-x-1/2 -translate-y-1/2 rounded-[50%] border-2 border-dashed"
              :class="pageStatus === 'detected' ? 'border-emerald-500' : 'border-emerald-400 animate-pulse'"
            />
          </template>

          <!-- Overlay saat error kamera -->
          <div
            v-if="pageStatus === 'error'"
            class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/85 px-6 text-center"
          >
            <CameraOff class="h-10 w-10 text-gray-300" />
            <p class="text-sm font-medium text-white">{{ errorMessage }}</p>
          </div>

          <!-- Overlay saat mengirim -->
          <div
            v-else-if="pageStatus === 'submitting'"
            class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/70 text-white"
          >
            <Loader2 class="h-7 w-7 animate-spin text-emerald-400" />
            <p class="text-sm text-gray-200">Mencatat kehadiran...</p>
          </div>
        </div>

        <!-- Status Detail -->
        <div class="mt-4 flex flex-col gap-4">
          <!-- Hasil absensi berhasil -->
          <div
            v-if="pageStatus === 'detected' && scanResult"
            class="flex items-center gap-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-4"
            role="status"
          >
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-lg font-semibold text-white"
            >
              {{ scanResult.name.charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-base font-semibold text-emerald-900">{{ scanResult.name }}</p>
              <p class="text-xs text-emerald-700">
                {{ scanResult.kelas }} · {{ scanResult.jam }} · {{ scanResult.banner }}
              </p>
            </div>
            <span
              class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
              :class="STATUS_META[scanResult.status].badge"
            >
              {{ STATUS_META[scanResult.status].label }}
            </span>
          </div>

          <!-- Wajah tidak dikenali -->
          <div
            v-else-if="pageStatus === 'unknown'"
            class="flex items-center gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3"
            role="alert"
          >
            <TriangleAlert class="h-5 w-5 shrink-0 text-red-600" />
            <p class="text-sm text-red-700">
              Wajah tidak dikenali. Pastikan wajah sudah terdaftar, lalu coba lagi.
            </p>
          </div>

          <!-- Ready: instruksi -->
          <p v-else-if="pageStatus === 'ready'" class="text-center text-sm text-gray-500">
            Posisikan wajah di tengah layar, lalu tekan <span class="font-medium text-slate-800">Mulai Scan</span>.
          </p>

          <!-- Scanning -->
          <p
            v-else-if="pageStatus === 'scanning'"
            class="flex items-center justify-center gap-2 text-center text-sm text-emerald-700"
          >
            <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600" />
            Mendeteksi wajah secara otomatis...
          </p>

          <!-- Error -->
          <div
            v-else-if="pageStatus === 'error'"
            class="flex items-center gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3"
            role="alert"
          >
            <TriangleAlert class="h-5 w-5 shrink-0 text-red-600" />
            <p class="text-sm text-red-700">{{ errorMessage }}</p>
          </div>

          <!-- Kontrol -->
          <div class="flex items-center justify-center gap-3">
            <base-button
              v-if="pageStatus === 'ready'"
              variant="solid"
              data-testid="mulai-scan"
              @click="startScan"
            >
              <ScanFace class="h-4 w-4" />
              Mulai Scan
            </base-button>

            <base-button
              v-else-if="pageStatus === 'scanning'"
              variant="outline"
              data-testid="hentikan-scan"
              @click="stopScan"
            >
              Hentikan Scan
            </base-button>

            <base-button
              v-else-if="pageStatus === 'submitting'"
              variant="outline"
              disabled
            >
              <Loader2 class="h-4 w-4 animate-spin" />
              Memproses...
            </base-button>

            <base-button
              v-else-if="pageStatus === 'detected'"
              variant="solid"
              data-testid="pindai-ulang"
              @click="startScan"
            >
              <RotateCcw class="h-4 w-4" />
              Pindai Ulang
            </base-button>

            <base-button v-else-if="pageStatus === 'unknown'" variant="solid" @click="startScan">
              <RotateCcw class="h-4 w-4" />
              Coba Lagi
            </base-button>

            <base-button v-else-if="pageStatus === 'error'" variant="solid" @click="init">
              <RotateCcw class="h-4 w-4" />
              Coba Lagi
            </base-button>
          </div>

          <p class="text-center text-xs text-gray-400">
            Absensi {{ session === 'masuk' ? 'masuk' : 'pulang' }} · hasil langsung tersimpan di server.
          </p>
        </div>
      </div>
    </main>

    <!-- Konfirmasi keluar mode kiosk -->
    <modal
      v-model="leaveModalOpen"
      title="Keluar dari kiosk absensi?"
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
import { CameraOff, CheckCircle2, Loader2, LogOut, RotateCcw, ScanFace, ShieldCheck, TriangleAlert } from '@lucide/vue'

type FaceApiModule = typeof import('face-api.js')

definePageMeta({
  layout: false,
})

/** State halaman: boot -> ready -> scanning -> submitting -> detected/unknown/error. */
type PageStatus = 'boot' | 'ready' | 'scanning' | 'submitting' | 'detected' | 'unknown' | 'error'

const api = useApi()
const session = ref<'masuk' | 'pulang'>('masuk')
/** Override manual: petugas memaksa sesi sendiri (kasus pulang cepat/sakit). */
const manualSession = ref(false)
/** Jendela absen dari server. */
const attendanceWindow = ref<{ pulang_from: string } | null>(null)

/** Konfirmasi sebelum keluar dari mode kiosk. */
const { leaveModalOpen, requestLeave, confirmLeave, cancelLeave } = useLeaveGuard()

const sessionOptions = [
  { value: 'masuk', label: 'Masuk' },
  { value: 'pulang', label: 'Pulang' },
] as const

/** Sesi menurut jam server (dipakai saat override manual tidak aktif). */
const sesiOtotomatis = computed<'masuk' | 'pulang'>(() => {
  const batas = attendanceWindow.value?.pulang_from
  if (!batas) return 'masuk'
  const [h, m] = batas.split(':').map(Number)
  const now = new Date()
  return now.getHours() * 60 + now.getMinutes() >= (h ?? 0) * 60 + (m || 0) ? 'pulang' : 'masuk'
})

const pilihSesi = (next: 'masuk' | 'pulang') => {
  session.value = next
  manualSession.value = true
}

const kembaliOtomatis = () => {
  manualSession.value = false
  session.value = sesiOtotomatis.value
}

interface ScanView {
  name: string
  kelas: string
  jam: string
  status: AttendanceStatus
  banner: string
}

const videoRef = ref<HTMLVideoElement | null>(null)

const pageStatus = ref<PageStatus>('boot')
const errorMessage = ref('')
const detecting = ref(false)
const scanResult = ref<ScanView | null>(null)

let faceapi: FaceApiModule | null = null
let stream: MediaStream | null = null
let scanTimer: ReturnType<typeof setInterval> | null = null
let pendingDescriptor: number[] | null = null
let pendingPhoto: string | null = null

/** Foto bukti frame saat wajah terdeteksi (data URI JPEG, opsional). */
const captureFrame = (video: HTMLVideoElement): string | null => {
  if (!video.videoWidth || !video.videoHeight) return null

  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight

  const context = canvas.getContext('2d')
  if (!context) return null

  try {
    context.drawImage(video, 0, 0, canvas.width, canvas.height)
    return canvas.toDataURL('image/jpeg', 0.7)
  } catch {
    return null
  }
}

/* ---------- Status chip ---------- */
const statusChip = computed(() => {
  switch (pageStatus.value) {
    case 'ready':
      return { label: 'Kamera siap', icon: CheckCircle2, class: 'border-emerald-200 bg-emerald-50 text-emerald-800', spin: false }
    case 'scanning':
      return { label: 'Memindai wajah', icon: Loader2, class: 'border-emerald-200 bg-emerald-50 text-emerald-800', spin: true }
    case 'submitting':
      return { label: 'Mencatat kehadiran', icon: Loader2, class: 'border-emerald-200 bg-emerald-50 text-emerald-800', spin: true }
    case 'detected':
      return { label: 'Absensi tercatat', icon: CheckCircle2, class: 'border-emerald-200 bg-emerald-50 text-emerald-800', spin: false }
    case 'unknown':
      return { label: 'Wajah tidak dikenali', icon: TriangleAlert, class: 'border-red-200 bg-red-50 text-red-700', spin: false }
    case 'error':
      return { label: 'Ada masalah', icon: TriangleAlert, class: 'border-red-200 bg-red-50 text-red-700', spin: false }
    default:
      return { label: 'Memuat model...', icon: Loader2, class: 'border-gray-200 bg-gray-50 text-gray-600', spin: true }
  }
})

/* ---------- Inisialisasi: model + kamera ---------- */

const init = async () => {
  pageStatus.value = 'boot'
  errorMessage.value = ''
  scanResult.value = null

  try {
    // Dynamic import hanya dijalankan di client (SSR aman).
    faceapi = await import('face-api.js')

    const { tinyFaceDetector, faceLandmark68Net, faceRecognitionNet } = faceapi.nets
    await Promise.all([
      tinyFaceDetector.loadFromUri('/models'),
      faceLandmark68Net.loadFromUri('/models'),
      faceRecognitionNet.loadFromUri('/models'),
    ])

    await startCamera()
    pageStatus.value = 'ready'
  } catch (error) {
    pageStatus.value = 'error'
    errorMessage.value = friendlyMessage(error)
    console.error('[scan.vue] gagal inisialisasi:', error)
  }
}

const friendlyMessage = (error: unknown): string => {
  const name = (error as { name?: string })?.name ?? ''
  const message = (error as { message?: string })?.message ?? ''

  if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
    return 'Izin kamera ditolak. Izinkan akses kamera lewat browser, lalu coba lagi.'
  }
  if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
    return 'Kamera tidak ditemukan di perangkat ini.'
  }
  if (name === 'NotReadableError' || name === 'TrackStartError') {
    return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi lain, lalu coba lagi.'
  }
  if (name === 'AbortError') {
    return 'Pengambilan kamera dibatalkan.'
  }
  if (message.includes('model')) {
    return 'Gagal memuat model pengenalan wajah. Pastikan folder /models tersedia, lalu coba lagi.'
  }
  return message || 'Terjadi kesalahan saat mengakses kamera.'
}

const startCamera = async () => {
  stream = await navigator.mediaDevices.getUserMedia({
    video: { width: 640, height: 480, facingMode: 'user' },
    audio: false,
  })

  const video = videoRef.value
  if (!video) {
    stream?.getTracks().forEach((track) => track.stop())
    stream = null
    throw new Error('Elemen video tidak ditemukan.')
  }

  video.srcObject = stream
  // Tunggu hingga video benar-benar bisa diputar sebelum deteksi frame.
  await video.play()
}

/* ---------- Deteksi wajah berulang ---------- */

const startScan = () => {
  if (!faceapi || !videoRef.value) return
  stopScanTimer()
  errorMessage.value = ''
  scanResult.value = null
  pendingDescriptor = null
  pendingPhoto = null
  pageStatus.value = 'scanning'
  scanTimer = setInterval(runDetection, 250)
}

const runDetection = async () => {
  const video = videoRef.value
  if (!faceapi || !video || detecting.value || video.readyState < 2) return

  detecting.value = true
  try {
    const result = await faceapi
      .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }))
      .withFaceLandmarks()
      .withFaceDescriptor()

    // Wajah belum terdeteksi: biarkan interval jalan terus.
    if (!result) return

    // Wajah terdeteksi: berhenti memindai lalu cocokkan di server.
    stopScanTimer()
    pendingDescriptor = Array.from(result.descriptor) // Float32Array -> number[128]
    pendingPhoto = captureFrame(video)
    await submitScan()
  } catch (error) {
    stopScanTimer()
    pageStatus.value = 'error'
    errorMessage.value = 'Gagal memproses frame kamera. Silakan coba lagi.'
    console.error('[scan.vue] error deteksi:', error)
  } finally {
    detecting.value = false
  }
}

const submitScan = async () => {
  if (!pendingDescriptor) return
  pageStatus.value = 'submitting'

  try {
    const res = await api.scan(pendingDescriptor, session.value, pendingPhoto ?? undefined)
    scanResult.value = {
      name: res.data.student?.full_name ?? '-',
      kelas: res.data.student?.classroom?.name ?? '-',
      jam: timeNow(),
      status: res.data.status.code,
      banner: res.meta.created ? 'Absensi tercatat' : 'Sudah tercatat sebelumnya',
    }
    pageStatus.value = 'detected'
  } catch (error) {
    if ((error as { data?: { code?: string } })?.data?.code === 'face_not_recognized') {
      pageStatus.value = 'unknown'
    } else {
      pageStatus.value = 'error'
      errorMessage.value = apiErrorMessage(error)
    }
  } finally {
    pendingDescriptor = null
    pendingPhoto = null
  }
}

const timeNow = () =>
  new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(new Date())

const stopScan = () => {
  stopScanTimer()
  pageStatus.value = 'ready'
}

/* ---------- Cleanup ---------- */

const stopScanTimer = () => {
  if (scanTimer) clearInterval(scanTimer)
  scanTimer = null
}

const releaseCamera = () => {
  stopScanTimer()
  stream?.getTracks().forEach((track) => track.stop())
  stream = null
  if (videoRef.value) videoRef.value.srcObject = null
}

onMounted(async () => {
  // Sesi awal dari jam server (override manual menyusul bila petugas pilih).
  try {
    const meta = await api.meta()
    attendanceWindow.value = meta.data.attendance_window
    if (!manualSession.value) session.value = sesiOtotomatis.value
  } catch {
    // Info jendela absen opsional; fallback ke 'masuk'.
  }

  init()
})

onUnmounted(() => {
  releaseCamera()
})
</script>
