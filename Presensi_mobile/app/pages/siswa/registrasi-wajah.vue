<script setup lang="ts">
import type { ApiFaceDescriptor } from '#layers/shared/app/composables/useApi'

useHead({ title: 'Registrasi Wajah · Absensi Digital' })

const router = useRouter()
const api = useApi()
const { student, fetchMe } = useStudentAuth()

const {
  videoRef,
  status: faceStatus,
  errorMessage: faceError,
  result: faceResult,
  init: initFace,
  startScan: startScanApi,
  stopScan: stopScanApi,
  stopCamera: stopCameraApi,
} = useFaceScanner()

/** Jumlah sampel wajah yang harus terdaftar sebelum absen tanpa PIN. */
const MAX_SAMPLES = 3

/** Sampel wajah yang sudah tersimpan di server. */
const samples = ref<ApiFaceDescriptor[]>([])
const samplesCaptured = computed(() => samples.value.length)
const lengkap = computed(() => samplesCaptured.value >= MAX_SAMPLES)

const isLoadingSamples = ref(true)
const isScanning = ref(false)
const isSaving = ref(false)
const notice = ref('')
const noticeType = ref<'success' | 'error' | 'info'>('info')

let noticeTimer: ReturnType<typeof setTimeout> | null = null
let resumeTimer: ReturnType<typeof setTimeout> | null = null

const showNotice = (type: 'success' | 'error' | 'info', message: string) => {
  noticeType.value = type
  notice.value = message

  if (noticeTimer) clearTimeout(noticeTimer)
  noticeTimer = setTimeout(() => {
    notice.value = ''
  }, 4000)
}

/** Teks status di dalam bingkai kamera. */
const statusText = computed(() => {
  if (isSaving.value) return 'Menyimpan sampel…'
  switch (faceStatus.value) {
    case 'loading':
      return 'Menyiapkan kamera…'
    case 'error':
      return 'Kamera tidak siap'
    case 'scanning':
      return 'Posisikan wajah di dalam lingkaran'
    case 'detected':
      return 'Wajah terdeteksi'
    default:
      return 'Siap memindai'
  }
})

/** Label tombol utama sesuai progres pendaftaran. */
const labelTombol = computed(() => {
  if (samplesCaptured.value === 0) return 'Mulai Daftar Wajah'
  if (samplesCaptured.value < MAX_SAMPLES) return `Lanjutkan (${samplesCaptured.value}/${MAX_SAMPLES})`
  return 'Registrasi Wajah Selesai'
})

const muatSampel = async () => {
  try {
    const response = await api.studentFaceDescriptors()
    samples.value = response.data
      .filter((item) => item.is_active !== false)
      .sort((a, b) => a.sample_no - b.sample_no)
  } catch {
    // Daftar sampel bersifat informasi; jangan gagalkan halaman karenanya.
  } finally {
    isLoadingSamples.value = false
  }
}

const mulaiPindai = async () => {
  if (isScanning.value || lengkap.value) return

  notice.value = ''
  isScanning.value = true

  // Elemen <video> baru dirender setelah isScanning=true.
  await nextTick()

  if (faceStatus.value !== 'ready' && faceStatus.value !== 'scanning') {
    await initFace()
  }

  if (faceStatus.value !== 'ready') {
    isScanning.value = false
    return
  }

  startScanApi()
}

const berhentiPindai = () => {
  if (resumeTimer) clearTimeout(resumeTimer)
  resumeTimer = null
  stopScanApi()
  stopCameraApi()
  isScanning.value = false
}

/**
 * Simpan satu sampel. Dipanggil otomatis tiap kali wajah terdeteksi, lalu
 * pemindaian dilanjutkan untuk sampel berikutnya sampai mencapai 3.
 */
const simpanSampel = async (descriptor: number[]) => {
  if (isSaving.value || samplesCaptured.value >= MAX_SAMPLES) return

  isSaving.value = true
  stopScanApi()

  const nomorSampel = samplesCaptured.value + 1

  try {
    const response = await api.studentRegisterFace(descriptor, { sample_no: nomorSampel })

    samples.value = [
      ...samples.value.filter((item) => item.sample_no !== response.data.sample_no),
      response.data,
    ].sort((a, b) => a.sample_no - b.sample_no)

    if (samplesCaptured.value >= MAX_SAMPLES) {
      stopScanApi()
      stopCameraApi()
      isScanning.value = false
      // Segarkan profil supaya kartu di halaman profil ikut hilang.
      fetchMe(true).catch(() => {})
      showNotice('success', 'Registrasi wajah lengkap! Semua sampel tersimpan.')
    } else {
      showNotice(
        'success',
        `Sampel ${response.data.sample_no} tersimpan (${samplesCaptured.value}/${MAX_SAMPLES}).`,
      )
      resumeTimer = setTimeout(() => {
        if (isScanning.value) startScanApi()
      }, 1400)
    }
  } catch (error) {
    stopCameraApi()
    isScanning.value = false
    showNotice('error', apiErrorMessage(error))
  } finally {
    isSaving.value = false
  }
}

watch(faceResult, (detected) => {
  if (!detected || !isScanning.value || isSaving.value) return
  void simpanSampel(detected.descriptor)
})

onMounted(() => {
  fetchMe().catch(() => {})
  muatSampel()
})

onUnmounted(() => {
  if (noticeTimer) clearTimeout(noticeTimer)
  if (resumeTimer) clearTimeout(resumeTimer)
})

function kembali() {
  router.push('/siswa/profil')
}
</script>

<template>
  <div class="min-h-dvh bg-slate-50 pb-10">
    <!-- ── Navbar atas ── -->
    <header
      class="sticky top-0 z-20 flex items-center gap-1 border-b border-slate-100 bg-white px-4 py-3"
    >
      <button
        type="button"
        class="-ml-1 flex size-10 items-center justify-center rounded-full text-slate-700 transition active:bg-slate-100"
        aria-label="Kembali"
        @click="kembali"
      >
        <AppIcon name="chevron-left" :size="24" />
      </button>

      <h1 class="text-base font-bold text-slate-900">Registrasi Wajah</h1>
    </header>

    <main class="px-5">
      <!-- ── Ringkasan progres ── -->
      <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3">
          <span
            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"
          >
            <AppIcon name="scan-face" :size="20" />
          </span>
          <div class="min-w-0">
            <h2 class="text-sm font-bold text-slate-900">
              {{ lengkap ? 'Wajah Sudah Terdaftar' : 'Daftarkan Wajah Anda' }}
            </h2>
            <p class="mt-1 text-xs leading-relaxed text-slate-400">
              {{
                lengkap
                  ? 'Wajah Anda sudah lengkap terdaftar dan siap dipakai untuk absensi.'
                  : `Dibutuhkan ${MAX_SAMPLES} sampel wajah agar bisa absen tanpa PIN.`
              }}
            </p>
          </div>
        </div>

        <!-- Stepper 3 sampel -->
        <div class="mt-5 flex items-center" aria-hidden="true">
          <template v-for="n in MAX_SAMPLES" :key="n">
            <div
              class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors"
              :class="
                n <= samplesCaptured
                  ? 'bg-emerald-500 text-white'
                  : 'bg-slate-100 text-slate-400 ring-1 ring-slate-200 ring-inset'
              "
            >
              <AppIcon v-if="n <= samplesCaptured" name="check" :size="16" />
              <span v-else>{{ n }}</span>
            </div>
            <div
              v-if="n < MAX_SAMPLES"
              class="mx-2 h-0.5 flex-1 rounded-full transition-colors"
              :class="n < samplesCaptured ? 'bg-emerald-400' : 'bg-slate-200'"
            />
          </template>
        </div>

        <p class="mt-2.5 text-xs font-semibold text-slate-500">
          {{ samplesCaptured }} dari {{ MAX_SAMPLES }} sampel tersimpan
        </p>

        <p
          v-if="notice"
          class="mt-3 rounded-xl border px-3 py-2 text-xs font-medium"
          :class="{
            'border-emerald-200 bg-emerald-50 text-emerald-700': noticeType === 'success',
            'border-red-200 bg-red-50 text-red-600': noticeType === 'error',
            'border-slate-200 bg-slate-50 text-slate-600': noticeType === 'info',
          }"
        >
          {{ notice }}
        </p>
      </section>

      <!-- ── Sudah lengkap ── -->
      <section v-if="lengkap" class="mt-4 rounded-2xl bg-white p-6 text-center shadow-sm">
        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-emerald-50">
          <AppIcon name="check-circle" :size="28" class="text-emerald-600" />
        </span>
        <h2 class="mt-4 text-lg font-bold text-slate-900">Registrasi Selesai</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-500">
          Semua {{ MAX_SAMPLES }} sampel wajah sudah tersimpan. Anda bisa absen dengan pemindaian
          wajah tanpa memasukkan PIN.
        </p>
        <NuxtLink to="/siswa/profil" class="btn-primary mt-6">
          <AppIcon name="chevron-left" :size="20" />
          <span>Kembali ke Profil</span>
        </NuxtLink>
      </section>

      <!-- ── Pemindaian ── -->
      <template v-else>
        <section class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
          <!-- Preview kamera -->
          <div v-if="isScanning" class="relative aspect-square w-full bg-slate-900">
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="h-full w-full -scale-x-100 object-cover"
            />

            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div
                class="h-[70%] w-[70%] rounded-full border-2 transition-colors"
                :class="{
                  'border-white/70': faceStatus === 'ready',
                  'border-amber-400': faceStatus === 'scanning',
                  'border-emerald-400': faceStatus === 'detected',
                  'border-red-400': faceStatus === 'error',
                }"
              />
            </div>

            <div
              class="absolute inset-x-3 bottom-3 rounded-full bg-black/55 px-4 py-1.5 text-center text-xs font-medium text-white backdrop-blur-sm"
            >
              {{ statusText }}
            </div>
          </div>

          <!-- Panduan sebelum kamera menyala -->
          <div v-else class="flex flex-col items-center px-5 py-8 text-center">
            <span class="flex size-16 items-center justify-center rounded-2xl bg-brand-50">
              <AppIcon name="scan-face" :size="30" class="text-brand-700" />
            </span>
            <h2 class="mt-4 text-base font-bold text-slate-900">
              {{ isLoadingSamples ? 'Memuat data wajah…' : 'Siap Mendaftarkan Wajah' }}
            </h2>
            <p class="mt-2 max-w-[19rem] text-sm leading-relaxed text-slate-500">
              Pastikan wajah terlihat jelas, pencahayaan cukup, dan lepas masker atau penutup
              wajah. Wajah akan dipindai otomatis {{ MAX_SAMPLES }} kali.
            </p>
          </div>

          <p
            v-if="faceError"
            class="flex items-start gap-1.5 border-t border-slate-100 px-4 py-3 text-xs text-red-500"
          >
            <AppIcon name="alert" :size="16" class="mt-0.5 shrink-0" />
            <span>{{ faceError }}</span>
          </p>
        </section>

        <!-- Tombol aksi -->
        <button
          v-if="!isScanning"
          type="button"
          class="btn-primary mt-4"
          :disabled="isSaving || faceStatus === 'loading' || isLoadingSamples"
          @click="mulaiPindai"
        >
          <AppIcon name="scan-face" :size="18" />
          <span>{{ labelTombol }}</span>
        </button>

        <button v-else type="button" class="btn-outline mt-4" @click="berhentiPindai">
          <AppIcon name="scan-face" :size="18" />
          <span>Berhenti Memindai</span>
        </button>

        <p class="mt-3 text-center text-xs text-slate-400">
          Sampel tersimpan otomatis tiap kali wajah terdeteksi.
        </p>
      </template>
    </main>
  </div>
</template>
