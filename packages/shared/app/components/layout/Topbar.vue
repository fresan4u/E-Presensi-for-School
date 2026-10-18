<template>
  <header
    class="flex h-16 shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6"
  >
    <!-- Sisi Kiri: Judul halaman aktif + sub-judul -->
    <div class="flex min-w-0 flex-col">
      <h1 class="truncate text-lg font-semibold tracking-tight text-slate-800">
        {{ pageTitle }}
      </h1>
      <p v-if="pageSubtitle" class="truncate text-xs text-gray-500">
        {{ pageSubtitle }}
      </p>
    </div>

    <!-- Sisi Kanan: status mesin, tanggal, notifikasi, profil -->
    <div class="flex shrink-0 items-center gap-4 lg:gap-5">
      <!-- Indikator status online mesin -->
      <div class="hidden items-center gap-2 md:flex">
        <span class="relative flex h-2.5 w-2.5">
          <span
            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
          />
          <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-600" />
        </span>
        <span class="text-xs font-medium text-slate-600">Mesin Online</span>
      </div>

      <!-- Hari & tanggal -->
      <div class="hidden text-right lg:block">
        <p class="text-xs font-medium text-slate-800">{{ todayLabel }}</p>
        <p class="text-[11px] text-gray-500">{{ todayDate }}</p>
      </div>

      <!-- Ikon notifikasi -->
      <button
        type="button"
        aria-label="Notifikasi"
        class="relative rounded-md p-2 text-gray-500 transition-colors hover:bg-slate-50 hover:text-slate-800 focus:outline-none"
      >
        <Bell class="h-5 w-5" />
        <span class="absolute right-1.5 top-1.5 flex h-2 w-2">
          <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75" />
          <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500" />
        </span>
      </button>

      <!-- Profil pengguna -->
      <div class="flex items-center gap-3 border-l border-gray-200 pl-4">
        <div
          class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-emerald-900 text-sm font-semibold text-white"
        >
          {{ userInitial }}
        </div>
        <div class="hidden flex-col md:flex">
          <span class="text-sm font-medium leading-tight text-slate-800">{{ userName }}</span>
          <span class="text-xs leading-tight text-gray-500">{{ userRole }}</span>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { Bell } from '@lucide/vue'

const props = defineProps<{
  /** Override judul halaman (diutamakan di atas meta.title & fallback path). */
  title?: string
  /** Override sub-judul halaman. */
  subtitle?: string
}>()

const route = useRoute()
const { user } = useAuth()

/** Judul fallback per path. */
const pageTitles: Record<string, string> = {
  '/': 'Dashboard',
  '/siswa': 'Data Siswa',
  '/registrasi-wajah': 'Data Siswa & Registrasi Wajah',
}

/** Sub-judul / deskripsi singkat per path. */
const pageSubtitles: Record<string, string> = {
  '/': 'Ringkasan kehadiran siswa hari ini',
  '/siswa': 'Data master seluruh siswa',
  '/registrasi-wajah': 'Kelola data siswa & daftarkan sampel wajah',
}

const pageTitle = computed(() => {
  if (props.title?.trim()) return props.title
  const metaTitle = route.meta.title
  if (typeof metaTitle === 'string' && metaTitle.trim()) return metaTitle
  return pageTitles[route.path] ?? 'Dashboard'
})

const pageSubtitle = computed(() => {
  if (props.subtitle?.trim()) return props.subtitle
  return pageSubtitles[route.path] ?? ''
})

const todayLabel = computed(() =>
  new Intl.DateTimeFormat('id-ID', { weekday: 'long' }).format(new Date()),
)

const todayDate = computed(() =>
  new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(
    new Date(),
  ),
)

const userName = computed(() => user.value?.name || 'Admin')
const userRole = computed(() => user.value?.role || 'Admin')
const userInitial = computed(() => userName.value.charAt(0).toUpperCase() || 'A')
</script>