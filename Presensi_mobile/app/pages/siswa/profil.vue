<script setup lang="ts">
import type { ApiFaceDescriptor } from '#layers/shared/app/composables/useApi'

useHead({ title: 'Profil · Absensi Digital' })

const router = useRouter()
const { student, fetchMe, logout } = useStudentAuth()
const api = useApi()

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

const isLoading = ref(false)

const avatarInisial = computed(() => inisial(student.value?.full_name ?? ''))

/** Informasi akun siswa yang ditampilkan (read-only). */
const infoAkun = computed(() => [
  { icon: 'user' as const, label: 'Nama Lengkap', value: student.value?.full_name ?? '—' },
  { icon: 'at-sign' as const, label: 'NIS', value: student.value?.nis ?? '—' },
  { icon: 'calendar' as const, label: 'Kelas', value: student.value?.classroom?.name ?? '—' },
])

/* ---------- Registrasi wajah ---------- */
const MAX_SAMPLES = 3

/** Sampel wajah yang sudah tersimpan di server. */
const samples = ref<ApiFaceDescriptor[]>([])
const samplesCaptured = computed(() => samples.value.length)

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

const loadSamples = async () => {
  try {
    const res = await api.studentFaceDescriptors()
    samples.value = res.data
  } catch {
    // Daftar sampel opsional; jangan gagalkan halaman profil karenanya.
  }
}

const startFaceScan = async () => {
  if (samplesCaptured.value >= MAX_SAMPLES) return

  notice.value = ''
  isScanning.value = true

  // Elemen <video> baru dirender setelah isScanning=true.
  await nextTick()

  await initFace()
  if (faceStatus.value !== 'ready') {
    isScanning.value = false
    return
  }

  startScanApi()
}

const stopFaceScan = () => {
  if (resumeTimer) clearTimeout(resumeTimer)
  resumeTimer = null
  stopScanApi()
  stopCameraApi()
  isScanning.value = false
}

/**
 * Simpan satu sampel. Dipanggil otomatis setiap kali wajah terdeteksi, lalu
 * pemindaian dilanjutkan untuk sampel berikutnya sampai mencapai 3.
 */
const saveSample = async (descriptor: number[]) => {
  if (isSaving.value || samplesCaptured.value >= MAX_SAMPLES) return

  isSaving.value = true
  stopScanApi()

  const nextSampleNo = samplesCaptured.value + 1

  try {
    const res = await api.studentRegisterFace(descriptor, { sample_no: nextSampleNo })
    samples.value = [...samples.value.filter((item) => item.sample_no !== res.data.sample_no), res.data]
      .sort((a, b) => a.sample_no - b.sample_no)

    if (samplesCaptured.value >= MAX_SAMPLES) {
      stopScanApi()
      stopCameraApi()
      isScanning.value = false
      showNotice('success', 'Registrasi wajah lengkap! Semua 3 sampel tersimpan.')
    } else {
      showNotice('success', `Sampel ${res.data.sample_no} tersimpan (${samplesCaptured.value}/${MAX_SAMPLES}).`)
      resumeTimer = setTimeout(() => {
        if (isScanning.value) startScanApi()
      }, 1200)
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
  void saveSample(detected.descriptor)
})

onMounted(() => {
  fetchMe().catch(() => {})
  loadSamples()
})

onUnmounted(() => {
  if (noticeTimer) clearTimeout(noticeTimer)
  if (resumeTimer) clearTimeout(resumeTimer)
})

async function onKeluar() {
  if (isLoading.value) return
  isLoading.value = true
  await logout()
  router.replace('/siswa/login')
}
</script>

<template>
  <div class="min-h-dvh bg-slate-50 pb-nav">
    <!-- ── Header hijau: avatar + identitas ── -->
    <section
      class="relative overflow-hidden rounded-b-3xl bg-brand-700 shadow-[0_12px_28px_-16px_rgba(2,6,23,0.45)]"
    >
      <header class="px-6 pt-[calc(env(safe-area-inset-top)+2.5rem)] pb-8 text-white">
        <h1 class="text-sm font-semibold tracking-wide text-white/80 uppercase">Profil</h1>

        <div class="mt-6 flex items-center gap-4">
          <span
            class="flex size-16 shrink-0 items-center justify-center rounded-full bg-white/15 text-xl font-bold ring-1 ring-white/25 ring-inset"
          >
            {{ avatarInisial }}
          </span>
          <div class="min-w-0">
            <p class="truncate text-xl font-extrabold tracking-tight">
              {{ student?.full_name ?? 'Siswa' }}
            </p>
            <p class="mt-0.5 text-sm text-white/70">
              Kelas {{ student?.classroom?.name ?? '—' }} · NIS {{ student?.nis ?? '—' }}
            </p>
          </div>
        </div>
      </header>
    </section>

    <main class="px-5">
      <!-- ── Kartu informasi akun ── -->
      <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="text-sm font-bold tracking-wide text-slate-900 uppercase">Informasi Akun</h2>

        <dl class="mt-4 divide-y divide-slate-100">
          <div
            v-for="row in infoAkun"
            :key="row.label"
            class="flex items-center gap-3 py-3.5 first:pt-0 last:pb-0"
          >
            <span
              class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"
            >
              <AppIcon :name="row.icon" :size="20" />
            </span>
            <dt class="text-sm text-slate-500">{{ row.label }}</dt>
            <dd class="ml-auto min-w-0 truncate text-right text-sm font-bold text-slate-900">
              {{ row.value }}
            </dd>
          </div>
        </dl>
      </section>

      <!-- ── Keamanan: ganti PIN ── -->
      <NuxtLink
        v-if="student?.can_change_pin !== false || student?.must_change_pin"
        to="/siswa/ganti-pin"
        class="mt-4 flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm transition active:scale-[0.99]"
      >
        <span
          class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"
        >
          <AppIcon name="lock" :size="20" />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-sm font-bold text-slate-900">Ganti PIN</span>
          <span class="mt-0.5 block text-xs text-slate-400">
            {{ student?.must_change_pin ? 'PIN bawaan wajib diganti' : 'PIN hanya bisa diganti sekali' }}
          </span>
        </span>
        <AppIcon name="chevron-right" :size="20" class="text-slate-300" />
      </NuxtLink>

      <!-- PIN sudah pernah diganti: bukan link, hanya info terkunci -->
      <div
        v-else
        class="mt-4 flex items-center gap-3 rounded-2xl bg-white p-4 opacity-80 shadow-sm"
      >
        <span
          class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400"
        >
          <AppIcon name="lock" :size="20" />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-sm font-bold text-slate-500">PIN Terkunci</span>
          <span class="mt-0.5 block text-xs text-slate-400">
            PIN sudah diganti sekali dan tidak dapat diubah lagi
          </span>
        </span>
      </div>

      <!-- ── Registrasi wajah ── -->
      <section v-if="student?.id" class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-sm font-bold tracking-wide text-slate-900 uppercase">Registrasi Wajah</h2>
            <p class="mt-1 text-xs text-slate-400">
              Daftarkan {{ MAX_SAMPLES }} sampel wajah agar bisa absen tanpa PIN.
            </p>
          </div>
          <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">
            {{ samplesCaptured }}/{{ MAX_SAMPLES }}
          </span>
        </div>

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

        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full bg-brand-600 transition-all"
            :style="{ width: `${(samplesCaptured / MAX_SAMPLES) * 100}%` }"
          />
        </div>

        <!-- Preview kamera -->
        <div
          v-if="isScanning"
          class="relative mt-4 aspect-square w-full overflow-hidden rounded-2xl bg-slate-900"
        >
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
            <template v-if="isSaving">Menyimpan sampel...</template>
            <template v-else-if="faceStatus === 'detected'">Wajah terdeteksi</template>
            <template v-else-if="faceStatus === 'scanning'">Posisikan wajah di dalam lingkaran</template>
            <template v-else>Menyiapkan kamera...</template>
          </div>
        </div>

        <p v-if="faceError" class="mt-2 flex items-start gap-1.5 text-xs text-red-500">
          <AppIcon name="alert" :size="16" class="mt-0.5 shrink-0" />
          <span>{{ faceError }}</span>
        </p>

        <button
          v-if="!isScanning"
          type="button"
          class="btn-primary mt-4 w-full"
          :disabled="samplesCaptured >= MAX_SAMPLES || faceStatus === 'loading'"
          @click="startFaceScan"
        >
          <AppIcon name="scan-face" :size="18" />
          <span>
            {{ samplesCaptured >= MAX_SAMPLES ? 'Registrasi wajah selesai' : 'Mulai Daftar Wajah' }}
          </span>
        </button>

        <button v-else type="button" class="btn-outline mt-4 w-full" @click="stopFaceScan">
          <AppIcon name="scan-face" :size="18" />
          <span>Berhenti Memindai</span>
        </button>

        <p v-if="samplesCaptured >= MAX_SAMPLES" class="mt-3 flex items-center justify-center gap-1.5 text-xs font-medium text-emerald-600">
          <AppIcon name="check-circle" :size="16" />
          Wajah sudah terdaftar lengkap
        </p>
      </section>

      <!-- ── Tombol keluar ── -->
      <button
        type="button"
        class="btn-outline mt-4 border-danger-100 text-danger-600"
        :disabled="isLoading"
        @click="onKeluar"
      >
        <AppIcon name="logout" :size="20" />
        <span>{{ isLoading ? 'Keluar…' : 'Keluar' }}</span>
      </button>
    </main>

    <SiswaBottomnav active="profil" />
  </div>
</template>
