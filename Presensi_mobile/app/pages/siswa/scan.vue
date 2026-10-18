<script setup lang="ts">
useHead({ title: 'Scan Wajah · Absensi Digital' })

const router = useRouter()
const api = useApi()
const { fetchMe } = useStudentAuth()

const {
  videoRef,
  status: faceStatus,
  errorMessage: faceError,
  result: faceResult,
  init: initFace,
  startScan: startFaceScan,
  stopScan: stopFaceScan,
} = useFaceScanner()

/** Sesi yang sedang diabsen. */
const session = ref<'masuk' | 'pulang'>('masuk')
const submitting = ref(false)
const notice = ref('')
const showHelp = ref(false)

/** Hasil scan terakhir (dibaca halaman berhasil). */
const lastScan = useState<ScanResult | null>('last-scan', () => null)

// Jangan keluar aplikasi jika tidak ada history (mis. dibuka langsung via URL).
function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/siswa')
}

onMounted(async () => {
  fetchMe().catch(() => {})
  await initFace()
})

/** Setiap descriptor terdeteksi -> langsung dikirim untuk dicocokkan 1:1. */
watch(faceResult, (hasil) => {
  if (hasil && !submitting.value) kirimAbsen(hasil.descriptor)
})

async function kirimAbsen(descriptor: number[]) {
  submitting.value = true
  notice.value = ''

  try {
    const res = await api.studentScan(descriptor, session.value)
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

    notice.value = apiErrorMessage(error)
    submitting.value = false
    // Ulangi pemindaian setelah gangguan sementara.
    startFaceScan()
  }
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

    <!-- ── Pilih sesi ── -->
    <div class="px-5 pt-4">
      <div class="flex rounded-xl bg-slate-100 p-1">
        <button
          v-for="opt in [{ value: 'masuk', label: 'Absen Masuk' }, { value: 'pulang', label: 'Absen Pulang' }]"
          :key="opt.value"
          type="button"
          :data-testid="`session-${opt.value}`"
          class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition"
          :class="session === opt.value ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'"
          @click="session = opt.value as 'masuk' | 'pulang'"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>

    <!-- ── Area kamera pemindai ── -->
    <main class="flex flex-1 flex-col px-5 pt-4">
      <div
        class="relative mx-2 h-[46vh] min-h-[320px] w-full flex-1 overflow-hidden rounded-3xl bg-slate-900 shadow-[0_8px_30px_-12px_rgba(2,6,23,0.45)]"
      >
        <!-- Feed kamera -->
        <video
          ref="videoRef"
          autoplay
          playsinline
          muted
          class="absolute inset-0 h-full w-full -scale-x-100 object-cover"
        />

        <!-- Badge status deteksi -->
        <span
          class="absolute top-4 left-4 z-10 flex items-center gap-1.5 rounded-full bg-black/45 px-3 py-1.5 text-[11px] font-bold tracking-wide text-scan uppercase backdrop-blur-sm"
        >
          <span
            class="size-1.5 rounded-full bg-scan"
            :class="faceStatus === 'scanning' ? 'animate-pulse' : ''"
          />
          {{ faceStatus === 'detected' ? 'Face Detected' : 'Kamera Aktif' }}
        </span>

        <!-- Bingkai pembidik wajah: kotak transparan + pinggiran sudut tebal -->
        <div
          class="absolute top-1/2 left-1/2 aspect-square w-[64%] max-w-[240px] -translate-x-1/2 -translate-y-1/2 border border-dashed border-scan/25"
        >
          <span class="absolute -top-0.5 -left-0.5 size-9 rounded-tl-xl border-t-[5px] border-l-[5px] border-scan" />
          <span class="absolute -top-0.5 -right-0.5 size-9 rounded-tr-xl border-t-[5px] border-r-[5px] border-scan" />
          <span class="absolute -bottom-0.5 -left-0.5 size-9 rounded-bl-xl border-b-[5px] border-l-[5px] border-scan" />
          <span class="absolute -right-0.5 -bottom-0.5 size-9 rounded-br-xl border-r-[5px] border-b-[5px] border-scan" />
        </div>

        <!-- Garis pemindai berjalan -->
        <div class="pointer-events-none absolute inset-x-0 top-0 h-full overflow-hidden">
          <div
            class="h-16 w-full bg-gradient-to-b from-transparent via-scan/15 to-scan/40"
            :class="faceStatus === 'scanning' ? 'animate-[scan-line_0.9s_ease-in-out_infinite]' : 'animate-[scan-line_2.4s_ease-in-out_infinite]'"
          />
        </div>

        <!-- Overlay: menyiapkan kamera -->
        <div
          v-if="faceStatus === 'loading'"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/70 text-white"
        >
          <span class="size-7 animate-spin rounded-full border-2 border-white/30 border-t-scan" />
          <p class="text-sm text-white/80">Menyiapkan kamera & model…</p>
        </div>

        <!-- Overlay: kamera bermasalah -->
        <div
          v-else-if="faceStatus === 'error'"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-4 bg-black/85 px-6 text-center text-white"
        >
          <AppIcon name="alert" :size="36" class="text-danger-500" />
          <p class="max-w-xs text-sm text-white/85">{{ faceError }}</p>
          <button type="button" class="btn-primary w-auto px-6" @click="initFace">
            Coba Lagi
          </button>
        </div>
      </div>

      <!-- ── Instruksi ── -->
      <p class="mt-5 text-center text-sm font-semibold text-slate-800">
        Posisikan wajah Anda di dalam area
      </p>
      <p class="mt-1.5 flex items-center justify-center gap-1.5 text-xs text-slate-400">
        <AppIcon name="user-scan" :size="15" />
        {{ statusText }}
      </p>

      <p
        v-if="notice"
        class="mt-4 rounded-xl bg-danger-50 px-4 py-3 text-center text-xs font-medium text-danger-600"
        role="alert"
      >
        {{ notice }}
      </p>

      <!-- ── Tombol mulai scan ── -->
      <button
        type="button"
        data-testid="mulai-scan"
        class="btn-primary mt-5 mb-6"
        :disabled="faceStatus !== 'ready' || submitting"
        @click="startFaceScan"
      >
        <AppIcon name="scan-face" :size="20" />
        <span>{{ faceStatus === 'scanning' || submitting ? 'Memindai…' : 'Mulai Scan' }}</span>
      </button>
    </main>

    <!-- Panel bantuan (ikon tanda tanya di navbar) -->
    <Teleport to="body">
      <div
        v-if="showHelp"
        class="fixed inset-0 z-50 flex items-end bg-black/60 p-4"
        role="dialog"
        aria-modal="true"
        @click.self="showHelp = false"
      >
        <div class="w-full rounded-t-3xl bg-white p-6 pb-[calc(env(safe-area-inset-bottom)+1.5rem)]">
          <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-slate-200" />
          <h2 class="text-base font-bold text-slate-900">Tips Scan Wajah</h2>
          <ul class="mt-3 space-y-2.5 text-sm text-slate-600">
            <li v-for="tip in ['Pastikan wajah berada di dalam bingkai hijau', 'Gunakan pencahayaan terang di sekitar Anda', 'Lepas masker dan kacamata']" :key="tip" class="flex gap-2.5">
              <AppIcon name="check" :size="18" class="mt-0.5 text-brand-600" :stroke-width="2.4" />
              <span>{{ tip }}</span>
            </li>
          </ul>
          <button type="button" class="btn-primary mt-5" @click="showHelp = false">Mengerti</button>
        </div>
      </div>
    </Teleport>
  </div>
</template>
