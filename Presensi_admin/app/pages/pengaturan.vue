<template>
  <div class="flex flex-col gap-6">
    <!-- Pop-up notifikasi (melayang di tengah atas layar) -->
    <Teleport to="body">
      <Transition name="toast">
        <div
          v-if="notice"
          class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex justify-center px-4"
        >
          <div
            class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
            :class="notice.type === 'success'
              ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
              : 'border-red-200 bg-red-50 text-red-700'"
            role="status"
            aria-live="polite"
            data-testid="notifikasi"
          >
            <component
              :is="notice.type === 'success' ? CheckCircle2 : TriangleAlert"
              class="mt-0.5 h-5 w-5 shrink-0"
            />
            <span class="min-w-0 flex-1 font-medium">{{ notice.text }}</span>
            <button
              type="button"
              class="-mr-1 shrink-0 rounded p-0.5 opacity-60 transition hover:opacity-100"
              aria-label="Tutup notifikasi"
              @click="closeNotice"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Ringkasan jendela absensi hari ini -->
    <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm md:col-span-2">
        <div class="flex flex-wrap items-center gap-3">
          <span
            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
            :class="todayStatus?.is_open
              ? 'bg-emerald-50 text-emerald-800'
              : 'bg-red-50 text-red-700'"
          >
            <span class="h-2 w-2 rounded-full" :class="todayStatus?.is_open ? 'bg-emerald-600' : 'bg-red-500'" />
            {{ todayStatus?.is_open ? 'Absensi mandiri dibuka' : 'Absensi mandiri ditutup' }}
          </span>
          <span class="text-sm font-medium text-slate-800">
            {{ formatDate(todayStatus?.date) }}
          </span>
          <span v-if="todayStatus?.is_holiday" class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">
            Libur: {{ todayStatus.holiday_name }}
          </span>
        </div>

        <p v-if="todayStatus" class="mt-3 text-sm text-gray-600">
          {{ todayStatus.message || 'Siswa bisa melakukan absensi mandiri saat ini.' }}
        </p>

        <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
          <div class="rounded-md bg-slate-50 px-3 py-2">
            <p class="text-xs text-slate-500">Masuk</p>
            <p class="font-semibold text-slate-800">{{ timeRange(todayStatus?.masuk_start, todayStatus?.masuk_end) }}</p>
          </div>
          <div class="rounded-md bg-slate-50 px-3 py-2">
            <p class="text-xs text-slate-500">Pulang</p>
            <p class="font-semibold text-slate-800">{{ timeRange(todayStatus?.pulang_from, todayStatus?.pulang_end) }}</p>
          </div>
          <div class="rounded-md bg-slate-50 px-3 py-2">
            <p class="text-xs text-slate-500">Hadir sampai</p>
            <p class="font-semibold text-slate-800">{{ todayStatus?.late_after ?? '—' }}</p>
          </div>
          <div class="rounded-md bg-slate-50 px-3 py-2">
            <p class="text-xs text-slate-500">Terlambat sampai</p>
            <p class="font-semibold text-slate-800">{{ todayStatus?.masuk_end ?? '—' }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jam server</p>
        <p class="mt-1 font-mono text-2xl font-bold text-slate-800">{{ serverClock }}</p>
        <p class="mt-2 text-xs text-gray-500">
          Semua jadwal memakai jam server. Perubahan langsung berlaku tanpa deploy ulang.
        </p>
      </div>
    </section>

    <!-- Tab navigasi -->
    <div class="flex flex-wrap gap-1 border-b border-gray-200">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors"
        :class="activeTab === tab.key
          ? 'border-emerald-800 text-emerald-900'
          : 'border-transparent text-gray-500 hover:text-slate-800'"
        @click="activeTab = tab.key"
      >
        <component :is="tab.icon" class="h-4 w-4" />
        {{ tab.label }}
      </button>
    </div>

    <!-- ── Tab Umum ── -->
    <div v-if="activeTab === 'umum'" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <base-card title="Absensi Mandiri" subtitle="Saklar utama absensi wajah dari HP siswa.">
        <form class="flex flex-col gap-5" @submit.prevent="saveSettings">
          <label class="flex items-start justify-between gap-4">
            <span>
              <span class="block text-sm font-medium text-slate-800">Aktifkan absensi mandiri</span>
              <span class="mt-0.5 block text-xs text-gray-500">
                Bila dimatikan, siswa hanya bisa absen lewat petugas (kiosk/input manual).
              </span>
            </span>
            <input v-model="settingsForm.selfScan" type="checkbox" class="toggle" />
          </label>

          <label class="flex items-start justify-between gap-4">
            <span>
              <span class="block text-sm font-medium text-slate-800">Kirim notifikasi WhatsApp</span>
              <span class="mt-0.5 block text-xs text-gray-500">
                Notifikasi ke wali untuk status hadir/terlambat. Kill-switch server (WA_ENABLED) tetap berlaku.
              </span>
            </span>
            <input v-model="settingsForm.waEnabled" type="checkbox" class="toggle" />
          </label>

          <div class="flex justify-end">
            <base-button type="submit" :loading="savingSettings">
              <Save class="h-4 w-4" />
              Simpan Pengaturan
            </base-button>
          </div>
        </form>
      </base-card>

      <base-card title="Status Sistem" subtitle="Informasi singkat konfigurasi yang sedang aktif.">
        <dl class="flex flex-col gap-3 text-sm">
          <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3">
            <dt class="text-gray-500">Absensi mandiri</dt>
            <dd class="font-medium text-slate-800">{{ settingsForm.selfScan ? 'Aktif' : 'Nonaktif' }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3">
            <dt class="text-gray-500">Geofence</dt>
            <dd class="font-medium text-slate-800">
              {{ locationForm.geofenceEnabled ? `Aktif (${locationForm.radius} m)` : 'Nonaktif' }}
            </dd>
          </div>
          <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3">
            <dt class="text-gray-500">Titik sekolah</dt>
            <dd class="font-mono text-xs font-medium text-slate-800">{{ locationSummary }}</dd>
          </div>
        </dl>
      </base-card>
    </div>

    <!-- ── Tab Jadwal ── -->
    <base-card
      v-if="activeTab === 'jadwal'"
      title="Jadwal Absensi Mingguan"
      subtitle="Atur jam presensi untuk setiap hari. Hari nonaktif tidak membuka absensi mandiri."
    >
      <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
          <thead class="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-600">
            <tr>
              <th class="px-3 py-3 font-semibold">Hari</th>
              <th class="px-3 py-3 font-semibold">Status</th>
              <th class="px-3 py-3 font-semibold">Presensi Dibuka</th>
              <th class="px-3 py-3 font-semibold">Presensi Ditutup</th>
              <th class="px-3 py-3 font-semibold">Presensi Pulang Dibuka</th>
              <th class="px-3 py-3 font-semibold">Presensi Pulang Ditutup</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in visibleSchedules" :key="row.day_of_week" class="border-b border-gray-100 last:border-0">
              <td class="px-3 py-2.5 font-medium text-slate-800">{{ row.day_name }}</td>
              <td class="px-3 py-2.5">
                <input v-model="row.is_active" type="checkbox" class="toggle" />
              </td>
              <td class="px-3 py-2.5">
                <input v-model="row.masuk_start" type="time" class="input font-mono" :disabled="!row.is_active" />
                <p v-if="row.is_active" class="mt-1 text-[11px] text-gray-500">
                  Hadir sampai <span class="font-mono font-medium text-slate-700">{{ hadirSampai(row) }}</span>
                </p>
              </td>
              <td class="px-3 py-2.5">
                <input v-model="row.masuk_end" type="time" class="input font-mono" :disabled="!row.is_active" />
              </td>
              <td class="px-3 py-2.5">
                <input v-model="row.pulang_start" type="time" class="input font-mono" :disabled="!row.is_active" />
              </td>
              <td class="px-3 py-2.5">
                <input v-model="row.pulang_end" type="time" class="input font-mono" :disabled="!row.is_active" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-xl text-xs text-gray-500">
          Status kehadiran otomatis: siswa yang presensi dalam {{ HADIR_MENIT }} menit pertama sejak presensi
          dibuka tercatat <span class="font-medium text-slate-700">Hadir</span>, setelah itu
          <span class="font-medium text-slate-700">Terlambat</span> sampai presensi ditutup.
        </p>
        <base-button type="button" :loading="savingSchedules" @click="saveSchedules">
          <Save class="h-4 w-4" />
          Simpan Jadwal
        </base-button>
      </div>
    </base-card>

    <!-- ── Tab Lokasi ── -->
    <div v-if="activeTab === 'lokasi'" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
      <base-card class="lg:col-span-5" title="Lokasi Sekolah" subtitle="Titik pusat area absensi mandiri siswa (geofencing).">
        <form class="flex flex-col gap-4" @submit.prevent="saveLocation">
          <label class="flex items-start justify-between gap-4">
            <span>
              <span class="block text-sm font-medium text-slate-800">Aktifkan geofence</span>
              <span class="mt-0.5 block text-xs text-gray-500">
                Server menolak absensi di luar radius. Bila nonaktif, koordinat tetap dicatat sebagai jejak.
              </span>
            </span>
            <input v-model="locationForm.geofenceEnabled" type="checkbox" class="toggle" />
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Latitude</span>
            <input v-model.number="locationForm.latitude" type="number" step="any" inputmode="decimal" class="input font-mono" placeholder="-6.2000000" />
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Longitude</span>
            <input v-model.number="locationForm.longitude" type="number" step="any" inputmode="decimal" class="input font-mono" placeholder="106.8166667" />
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Radius (meter)</span>
            <input v-model.number="locationForm.radius" type="number" min="10" max="5000" step="10" inputmode="numeric" class="input font-mono" />
            <span class="text-xs text-gray-500">10–5000 m. Naikkan bila sinyal GPS di sekolah kurang akurat.</span>
          </label>

          <div class="flex flex-wrap gap-3">
            <base-button type="button" variant="outline" :disabled="locating" @click="useCurrentLocation">
              <LocateFixed class="h-4 w-4" :class="locating ? 'animate-pulse' : ''" />
              {{ locating ? 'Mengambil GPS…' : 'Gunakan lokasi saya' }}
            </base-button>
            <base-button type="submit" :loading="savingLocation">
              <Save class="h-4 w-4" />
              Simpan Lokasi
            </base-button>
          </div>

          <p v-if="locationUpdatedAt" class="text-xs text-gray-500">
            Terakhir disimpan: {{ locationUpdatedAt }}
          </p>
        </form>
      </base-card>

      <base-card class="lg:col-span-7" title="Pratinjau Peta" subtitle="Titik merah adalah pusat area absensi (OpenStreetMap).">
        <div class="overflow-hidden rounded-md border border-gray-200">
          <iframe
            v-if="previewUrl"
            :src="previewUrl"
            title="Pratinjau lokasi sekolah"
            class="h-80 w-full"
            loading="lazy"
          />
          <div v-else class="flex h-80 items-center justify-center bg-slate-50 px-6 text-center text-sm text-gray-500">
            Isi latitude & longitude yang valid untuk melihat pratinjau peta.
          </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-4">
          <a
            v-if="previewUrl"
            :href="mapLink"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-800 hover:underline"
          >
            <MapPin class="h-4 w-4" />
            Buka di OpenStreetMap
          </a>
          <span class="text-xs" :class="locationForm.configured ? 'text-emerald-700' : 'text-amber-700'">
            {{ locationForm.configured ? 'Titik tersimpan di database.' : 'Masih memakai fallback config; simpan untuk mengaktifkan.' }}
          </span>
        </div>

        <div class="mt-4 flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          <Info class="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            Ambil koordinat dari Google Maps (klik kanan di gerbang sekolah) atau tekan
            <span class="font-medium">Gunakan lokasi saya</span> saat berada di area sekolah.
            Aplikasi siswa membaca titik ini setiap kali scan, jadi perubahan langsung berlaku.
          </p>
        </div>
      </base-card>
    </div>

    <!-- ── Tab Tahun Ajaran ── -->
    <div v-if="activeTab === 'tahun'" class="grid grid-cols-1 gap-6 lg:grid-cols-5">
      <base-card class="lg:col-span-2" :title="academicYearForm.id ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran'">
        <form class="flex flex-col gap-4" @submit.prevent="saveAcademicYear">
          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Label</span>
            <input
              v-model="academicYearForm.label"
              type="text"
              required
              maxlength="20"
              class="input font-mono"
              placeholder="2026/2027"
            />
            <span class="text-xs text-gray-500">Format tahun ajaran: <span class="font-mono">2026/2027</span>.</span>
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Tanggal mulai</span>
            <input v-model="academicYearForm.start_date" type="date" required class="input font-mono" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Tanggal selesai</span>
            <input v-model="academicYearForm.end_date" type="date" required class="input font-mono" />
          </label>
          <label class="flex items-start justify-between gap-4">
            <span>
              <span class="block text-sm font-medium text-slate-800">Jadikan tahun ajaran aktif</span>
              <span class="mt-0.5 block text-xs text-gray-500">
                Tahun aktif menjadi default filter data siswa dan absensi. Hanya satu tahun yang bisa aktif.
              </span>
            </span>
            <input v-model="academicYearForm.is_active" type="checkbox" class="toggle" />
          </label>
          <div class="flex flex-wrap gap-3">
            <base-button type="submit" :loading="savingAcademicYear">
              <Save class="h-4 w-4" />
              {{ academicYearForm.id ? 'Simpan Perubahan' : 'Tambah' }}
            </base-button>
            <base-button v-if="academicYearForm.id" type="button" variant="outline" @click="resetAcademicYearForm">
              Batal
            </base-button>
          </div>
        </form>
      </base-card>

      <base-card class="lg:col-span-3" title="Daftar Tahun Ajaran">
        <p v-if="loadingAcademicYears" class="py-8 text-center text-sm text-gray-500">
          <Loader2 class="mx-auto h-5 w-5 animate-spin" />
        </p>
        <p
          v-else-if="!academicYears.length"
          class="rounded-md border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500"
        >
          Belum ada tahun ajaran. Tambahkan satu untuk mulai menata data siswa.
        </p>
        <ul v-else class="divide-y divide-gray-100">
          <li
            v-for="year in academicYears"
            :key="year.id"
            class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="min-w-0">
              <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800">
                {{ year.label }}
                <span
                  v-if="year.is_active"
                  class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800"
                >
                  Aktif
                </span>
              </p>
              <p class="text-xs text-gray-500">
                {{ formatDate(year.start_date) }} – {{ formatDate(year.end_date) }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <base-button
                v-if="!year.is_active"
                variant="outline"
                :loading="activatingYearId === year.id"
                @click="activateAcademicYear(year)"
              >
                Aktifkan
              </base-button>
              <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-slate-100 hover:text-slate-800"
                title="Ubah tahun ajaran"
                :aria-label="`Ubah tahun ajaran ${year.label}`"
                @click="editAcademicYear(year)"
              >
                <Pencil class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                title="Hapus tahun ajaran"
                :aria-label="`Hapus tahun ajaran ${year.label}`"
                :disabled="year.is_active"
                @click="removeAcademicYear(year)"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </li>
        </ul>
      </base-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  CalendarClock,
  CalendarDays,
  CalendarRange,
  CheckCircle2,
  Info,
  Loader2,
  LocateFixed,
  MapPin,
  Pencil,
  Save,
  Settings,
  Trash2,
  TriangleAlert,
  X,
} from '@lucide/vue'
import type { Component } from 'vue'

definePageMeta({
  layout: 'default',
  title: 'Pengaturan',
})

const api = useApi()

type TabKey = 'umum' | 'jadwal' | 'libur' | 'lokasi' | 'tahun'

const tabs: ReadonlyArray<{ key: TabKey; label: string; icon: Component }> = [
  { key: 'umum', label: 'Umum', icon: Settings },
  { key: 'jadwal', label: 'Jadwal Absensi', icon: CalendarClock },
  { key: 'lokasi', label: 'Lokasi', icon: MapPin },
  { key: 'tahun', label: 'Tahun Ajaran', icon: CalendarRange },
]

const activeTab = ref<TabKey>('umum')

/* ------------------------------------------------------------------ */
/* Pop-up notifikasi                                                   */
/* ------------------------------------------------------------------ */

/** Lama pop-up tampil (ms): sukses singkat, error lebih lama supaya sempat dibaca. */
const TOAST_SUKSES_MS = 4000
const TOAST_ERROR_MS = 6000

const notice = ref<{ type: 'success' | 'error'; text: string } | null>(null)
let toastTimer: ReturnType<typeof setTimeout> | undefined

const closeNotice = () => {
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = undefined
  notice.value = null
}

const showNotice = (type: 'success' | 'error', text: string) => {
  if (toastTimer) clearTimeout(toastTimer)
  notice.value = { type, text }
  toastTimer = setTimeout(closeNotice, type === 'success' ? TOAST_SUKSES_MS : TOAST_ERROR_MS)
}

const todayStatus = ref<ApiSettingsResponse['status'] | null>(null)

/* Umum */
const savingSettings = ref(false)
const settingsForm = reactive({
  selfScan: true,
  waEnabled: true,
})

/* Jadwal */
const schedules = ref<ApiAttendanceSchedule[]>([])
const savingSchedules = ref(false)
/** Hari yang tidak ditampilkan di tabel jadwal. */
const HIDDEN_DAYS = ['Sabtu', 'Minggu']

const visibleSchedules = computed(() =>
  schedules.value.filter((row) => !HIDDEN_DAYS.includes(row.day_name)),
)

/**
 * Lama status "hadir" sejak presensi dibuka (menit). Nilai ini harus sama
 * dengan UpdateAttendanceSchedulesRequest::HADIR_MENIT di backend; server
 * yang menghitung batas sebenarnya, di sini hanya untuk tampilan.
 */
const HADIR_MENIT = 60

/** Menit sejak tengah malam dari "HH:MM" (null bila format tidak valid). */
const keMenit = (jam: string | null | undefined): number | null => {
  const cocok = /^(\d{2}):(\d{2})/.exec(jam ?? '')
  return cocok ? Number(cocok[1]) * 60 + Number(cocok[2]) : null
}

const keJam = (menit: number) =>
  `${String(Math.floor(menit / 60)).padStart(2, '0')}:${String(menit % 60).padStart(2, '0')}`

/** Jam batas hadir untuk satu baris jadwal (presensi dibuka + 60 menit). */
const hadirSampai = (row: ApiAttendanceSchedule) => {
  const mulai = keMenit(row.masuk_start)
  if (mulai === null) return '—'

  const selesai = keMenit(row.masuk_end)
  return keJam(Math.min(mulai + HADIR_MENIT, selesai ?? 23 * 60 + 59, 23 * 60 + 59))
}

/* Lokasi */
const savingLocation = ref(false)
const locating = ref(false)
const locationUpdatedAt = ref<string | null>(null)
const locationForm = reactive({
  latitude: -6.18841,
  longitude: 106.924823,
  radius: 100,
  geofenceEnabled: false,
  configured: false,
})

/* Tahun ajaran */
const academicYears = ref<ApiAcademicYear[]>([])
const loadingAcademicYears = ref(false)
const savingAcademicYear = ref(false)
const activatingYearId = ref<number | null>(null)
const academicYearForm = reactive({
  id: null as number | null,
  label: '',
  start_date: '',
  end_date: '',
  is_active: false,
})

/* Jam server */
const serverTime = ref<Date | null>(null)
let clockTimer: ReturnType<typeof setInterval> | undefined

const serverClock = computed(() => {
  // Sebelum data pengaturan datang, tampilkan placeholder. Memakai
  // `new Date()` di sini membuat jam hasil SSR beda dengan jam klien dan
  // memicu warning hydration mismatch.
  if (!serverTime.value) return '--:--:--'
  const value = serverTime.value
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).format(value)
})

const yearOptions = computed(() => {
  const current = new Date().getFullYear()
  return [current - 3, current - 2, current - 1, current, current + 1, current + 2]
})

const formatDate = (value?: string | null) => {
  if (!value) return '—'
  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date)
}

const formatDateTime = (value: string) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

const timeRange = (start?: string | null, end?: string | null) =>
  start && end ? `${start}–${end}` : '—'

const locationSummary = computed(() =>
  `${locationForm.latitude.toFixed(6)}, ${locationForm.longitude.toFixed(6)}`,
)

const isValidCoordinate = computed(
  () =>
    Number.isFinite(locationForm.latitude)
    && Number.isFinite(locationForm.longitude)
    && locationForm.latitude >= -90
    && locationForm.latitude <= 90
    && locationForm.longitude >= -180
    && locationForm.longitude <= 180,
)

const formatCoordinate = (value: number) => value.toFixed(6)

const previewUrl = computed(() => {
  if (!isValidCoordinate.value) return null
  const lat = locationForm.latitude
  const lng = locationForm.longitude
  const delta = 0.004
  const bbox = [
    formatCoordinate(lng - delta),
    formatCoordinate(lat - delta),
    formatCoordinate(lng + delta),
    formatCoordinate(lat + delta),
  ].join(',')
  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${formatCoordinate(lat)},${formatCoordinate(lng)}`
})

const mapLink = computed(
  () => `https://www.openstreetmap.org/?mlat=${locationForm.latitude}&mlon=${locationForm.longitude}#map=18/${locationForm.latitude}/${locationForm.longitude}`,
)

const applySettings = (response: ApiSettingsResponse) => {
  todayStatus.value = response.status
  serverTime.value = new Date(response.status.server_time)

  settingsForm.selfScan = response.data.attendance.self_scan_enabled
  settingsForm.waEnabled = response.data.notification.wa_enabled

  const location = response.data.location
  locationForm.latitude = location.latitude
  locationForm.longitude = location.longitude
  locationForm.radius = location.radius_meter
  locationForm.geofenceEnabled = location.geofence_enabled
  locationForm.configured = location.configured
  locationUpdatedAt.value = location.updated_at
    ? formatDateTime(location.updated_at)
    : null
}

const loadAll = async () => {
  try {
    const [settings, scheduleResponse] = await Promise.all([
      api.settings(),
      api.attendanceSchedules(),
    ])
    applySettings(settings)
    schedules.value = scheduleResponse.data
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  }
}

const loadAcademicYears = async () => {
  loadingAcademicYears.value = true
  try {
    const response = await api.academicYears()
    academicYears.value = response.data
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    loadingAcademicYears.value = false
  }
}

const saveSettings = async () => {
  savingSettings.value = true
  closeNotice()
  try {
    const response = await api.updateSettings({
      attendance: { self_scan_enabled: settingsForm.selfScan },
      notification: { wa_enabled: settingsForm.waEnabled },
    })
    applySettings(response)
    showNotice('success', 'Pengaturan umum berhasil disimpan.')
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    savingSettings.value = false
  }
}

const saveSchedules = async () => {
  savingSchedules.value = true
  closeNotice()
  try {
    const response = await api.updateAttendanceSchedules(
      schedules.value.map((row) => ({
        day_of_week: row.day_of_week,
        is_active: row.is_active,
        masuk_start: row.masuk_start,
        masuk_end: row.masuk_end,
        // Dihitung server dari jam presensi dibuka (+ HADIR_MENIT); nilai
        // yang dikirim dari sini diabaikan.
        handover_at: null,
        pulang_start: row.pulang_start,
        pulang_end: row.pulang_end,
        late_after: null,
      })),
    )
    schedules.value = response.data

    // Ringkasan hari ini ikut berubah bila yang diedit adalah hari ini.
    const settings = await api.settings()
    applySettings(settings)

    showNotice('success', 'Jadwal absensi berhasil diperbarui.')
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    savingSchedules.value = false
  }
}

const resetAcademicYearForm = () => {
  academicYearForm.id = null
  academicYearForm.label = ''
  academicYearForm.start_date = ''
  academicYearForm.end_date = ''
  academicYearForm.is_active = false
}

const editAcademicYear = (year: ApiAcademicYear) => {
  academicYearForm.id = year.id
  academicYearForm.label = year.label
  academicYearForm.start_date = year.start_date
  academicYearForm.end_date = year.end_date
  academicYearForm.is_active = year.is_active
}

const saveAcademicYear = async () => {
  if (academicYearForm.end_date && academicYearForm.start_date && academicYearForm.end_date <= academicYearForm.start_date) {
    showNotice('error', 'Tanggal selesai harus setelah tanggal mulai.')
    return
  }

  savingAcademicYear.value = true
  closeNotice()
  try {
    const payload = {
      label: academicYearForm.label.trim(),
      start_date: academicYearForm.start_date,
      end_date: academicYearForm.end_date,
      is_active: academicYearForm.is_active,
    }

    if (academicYearForm.id) {
      await api.updateAcademicYear(academicYearForm.id, payload)
      showNotice('success', 'Tahun ajaran berhasil diperbarui.')
    } else {
      await api.createAcademicYear(payload)
      showNotice('success', 'Tahun ajaran berhasil ditambahkan.')
    }

    resetAcademicYearForm()
    await loadAcademicYears()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    savingAcademicYear.value = false
  }
}

const activateAcademicYear = async (year: ApiAcademicYear) => {
  activatingYearId.value = year.id
  closeNotice()
  try {
    const response = await api.activateAcademicYear(year.id)
    showNotice('success', response.message || `Tahun ajaran ${year.label} diaktifkan.`)
    if (academicYearForm.id === year.id) academicYearForm.is_active = true
    await loadAcademicYears()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    activatingYearId.value = null
  }
}

const removeAcademicYear = async (year: ApiAcademicYear) => {
  if (year.is_active) return
  if (!window.confirm(`Hapus tahun ajaran "${year.label}"?`)) return

  closeNotice()
  try {
    await api.deleteAcademicYear(year.id)
    if (academicYearForm.id === year.id) resetAcademicYearForm()
    await loadAcademicYears()
    showNotice('success', 'Tahun ajaran dihapus.')
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  }
}

const useCurrentLocation = () => {
  if (!navigator.geolocation) {
    showNotice('error', 'Perangkat/browser ini belum mendukung GPS.')
    return
  }

  locating.value = true
  navigator.geolocation.getCurrentPosition(
    (position) => {
      locationForm.latitude = Number(position.coords.latitude.toFixed(6))
      locationForm.longitude = Number(position.coords.longitude.toFixed(6))
      locating.value = false
      showNotice('success', 'Koordinat GPS berhasil diambil. Jangan lupa tekan Simpan Lokasi.')
    },
    () => {
      locating.value = false
      showNotice('error', 'Gagal mengambil lokasi. Izinkan akses GPS/lokasi lalu coba lagi.')
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  )
}

const saveLocation = async () => {
  if (!isValidCoordinate.value) {
    showNotice('error', 'Latitude/longitude tidak valid.')
    return
  }

  if (locationForm.radius < 10 || locationForm.radius > 5000) {
    showNotice('error', 'Radius harus antara 10 dan 5000 meter.')
    return
  }

  savingLocation.value = true
  closeNotice()
  try {
    const response = await api.updateSchoolLocation({
      latitude: locationForm.latitude,
      longitude: locationForm.longitude,
      radius_meter: locationForm.radius,
      geofence_enabled: locationForm.geofenceEnabled,
    })
    locationForm.configured = response.data.configured
    locationUpdatedAt.value = response.data.updated_at
      ? formatDateTime(response.data.updated_at)
      : null
    showNotice('success', response.message || 'Lokasi sekolah berhasil disimpan.')
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    savingLocation.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadAll(), loadAcademicYears()])

  // Jam server: mulai dari waktu server lalu maju tiap detik.
  clockTimer = setInterval(() => {
    if (serverTime.value) {
      serverTime.value = new Date(serverTime.value.getTime() + 1000)
    }
  }, 1000)
})

onBeforeUnmount(() => {
  if (clockTimer) clearInterval(clockTimer)
  if (toastTimer) clearTimeout(toastTimer)
})
</script>

<style scoped>
.input {
  width: 100%;
  border-radius: 0.375rem;
  border: 1px solid #d1d5db;
  background: #fff;
  padding: 0.5rem 0.625rem;
  font-size: 0.875rem;
  color: #1e293b;
}
.input:focus {
  outline: none;
  border-color: #064e3b;
}
.input:disabled {
  background: #f8fafc;
  color: #94a3b8;
}
.toggle {
  width: 2.75rem;
  height: 1.5rem;
  flex-shrink: 0;
  appearance: none;
  border-radius: 9999px;
  background: #e2e8f0;
  position: relative;
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.toggle::after {
  content: '';
  position: absolute;
  top: 0.25rem;
  left: 0.25rem;
  width: 1rem;
  height: 1rem;
  border-radius: 9999px;
  background: #fff;
  box-shadow: 0 1px 2px rgb(15 23 42 / 0.25);
  transition: transform 0.15s ease;
}
.toggle:checked {
  background: #064e3b;
}
.toggle:checked::after {
  transform: translateX(1.25rem);
}

/* Animasi pop-up: turun dari atas, lalu memudar saat hilang */
.toast-enter-active,
.toast-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}
</style>