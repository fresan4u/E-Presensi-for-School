<template>
  <div class="flex flex-col gap-6">
    <!-- Toolbar Aksi -->
    <div class="flex flex-col gap-3">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <!-- Pencarian -->
        <div class="w-full lg:max-w-xs">
          <base-input v-model="search" type="search" placeholder="Cari nama siswa / NIS...">
            <template #prefix>
              <Search
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
              />
            </template>
          </base-input>
        </div>

        <!-- Export -->
        <div class="flex lg:justify-end">
          <base-button
            variant="solid"
            :class="BTN_GRADIENT"
            data-testid="export-csv"
            @click="exportCsv"
          >
            <Download class="h-4 w-4" />
            Export
          </base-button>
        </div>
      </div>

      <!-- Filter dropdown -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Kelas</span>
          <select v-model="filters.classroomId" :class="SELECT_CLASS">
            <option :value="null">Semua Kelas</option>
            <option v-for="room in classrooms" :key="room.id" :value="room.id">
              {{ room.name }}
            </option>
          </select>
        </label>

        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Metode Absensi</span>
          <select v-model="filters.method" :class="SELECT_CLASS">
            <option value="">Semua Metode</option>
            <option v-for="opt in methodOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </label>

        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Status</span>
          <select v-model="filters.status" :class="SELECT_CLASS">
            <option value="">Semua Status</option>
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </label>
      </div>
    </div>

    <p
      v-if="errorMessage"
      class="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      role="alert"
    >
      <TriangleAlert class="h-4 w-4 shrink-0" />
      {{ errorMessage }}
    </p>

    <!-- Tabel Data Absensi -->
    <div class="flex flex-col gap-4">
      <data-table :columns="columns" :rows="rows" :empty-text="emptyText">
        <template #cell-metode="{ value }">
          <span class="text-sm text-gray-600">{{ methodLabel(String(value)) }}</span>
        </template>
        <template #cell-status="{ value }">
          <span
            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="STATUS_META[value as AttendanceStatus].badge"
          >
            {{ STATUS_META[value as AttendanceStatus].label }}
          </span>
        </template>
      </data-table>

      <!-- Pagination: panah kiri, penanda halaman, panah kanan (di tengah) -->
      <div class="flex flex-col items-center gap-2">
        <div class="flex items-center gap-3">
          <button
            type="button"
            :class="[PAGE_BTN, BTN_GRADIENT]"
            :disabled="page <= 1"
            aria-label="Halaman sebelumnya"
            data-testid="page-prev"
            @click="goToPage(page - 1)"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>

          <span
            class="min-w-20 rounded-md border border-[#006FBE]/20 bg-[#006FBE]/5 px-4 py-2 text-center text-sm font-semibold tabular-nums text-[#004A80]"
            data-testid="page-indicator"
            aria-live="polite"
          >
            {{ page }} / {{ totalPages }}
          </span>

          <button
            type="button"
            :class="[PAGE_BTN, BTN_GRADIENT]"
            :disabled="page >= totalPages"
            aria-label="Halaman berikutnya"
            data-testid="page-next"
            @click="goToPage(page + 1)"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>

        <p class="text-xs text-gray-500">
          Menampilkan {{ fromRow }}–{{ toRow }} dari {{ totalRows }} data
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ChevronLeft, ChevronRight, Download, Search, TriangleAlert } from '@lucide/vue'
import type { TableColumn } from '#layers/shared/app/components/table/DataTable.vue'
import type { ApiAttendance, ApiClassroom } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'petugas',
  title: 'Data Absensi',
})

/* ------------------------------------------------------------------ */
/* Gaya                                                                */
/* ------------------------------------------------------------------ */

// Gradient biru: tombol Export dan panah pagination
const BTN_GRADIENT =
  'bg-gradient-to-b from-[#006FBE] to-[#004A80] text-white shadow-md shadow-[#006FBE]/30 hover:brightness-110'

// Dasar tombol panah pagination (nonaktif = redup, tanpa efek hover)
const PAGE_BTN =
  'inline-flex h-9 w-9 items-center justify-center rounded-md transition disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:brightness-100'

// Dropdown filter: fokus berwarna biru
const SELECT_CLASS =
  'w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 transition-colors focus:border-[#006FBE] focus:outline-none focus:ring-1 focus:ring-[#006FBE]/30'

const api = useApi()

/* ---------- Status yang ditampilkan ---------- */
/**
 * Halaman ini hanya memuat absensi yang dicatat siswa sendiri (hadir /
 * terlambat). Izin, sakit, dan alpa dikelola di halaman Rekap Harian.
 */
const VISIBLE_STATUSES: AttendanceStatus[] = ['hadir', 'terlambat']

/* ---------- Filter ---------- */
const filters = reactive({
  classroomId: null as number | null,
  method: '',
  status: '',
})

const methodOptions = (Object.keys(METHOD_LABEL) as AttendanceMethod[]).map((value) => ({
  value,
  label: METHOD_LABEL[value],
}))

const statusOptions = ATTENDANCE_STATUSES.filter((value) => VISIBLE_STATUSES.includes(value)).map(
  (value) => ({
    value,
    label: STATUS_META[value].label,
  }),
)

const classrooms = ref<ApiClassroom[]>([])

/* ---------- Kolom tabel ---------- */
const columns: TableColumn[] = [
  { key: 'nis', label: 'NIS' },
  { key: 'name', label: 'Nama Siswa' },
  { key: 'kelas', label: 'Kelas' },
  { key: 'tanggal', label: 'Tanggal' },
  { key: 'jamMasuk', label: 'Jam Masuk' },
  { key: 'jamKeluar', label: 'Jam Keluar' },
  { key: 'metode', label: 'Metode' },
  { key: 'status', label: 'Status' },
]

/* ---------- Ambil data dari API ---------- */
const PER_PAGE = 10
const page = ref(1)
const rows = ref<Record<string, unknown>[]>([])
const totalRows = ref(0)
const totalPages = ref(1)
const search = ref('')
const errorMessage = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null

const formatShortDate = (value: string) =>
  new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(
    new Date(`${value}T00:00:00`),
  )

const formatTime = (value: string) =>
  new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))

const toRecord = (attendance: ApiAttendance) => {
  const isMasuk = attendance.session.code === 'masuk'
  const time = formatTime(attendance.checked_at)

  return {
    id: attendance.id,
    nis: attendance.student?.nis ?? '-',
    name: attendance.student?.full_name ?? '-',
    kelas: attendance.student?.classroom?.name ?? '-',
    tanggal: formatShortDate(attendance.attendance_date),
    jamMasuk: isMasuk ? time : '--:--',
    jamKeluar: isMasuk ? '--:--' : time,
    metode: attendance.method.code.replace('_', ' '),
    status: attendance.status.code,
    catatan: attendance.note ?? undefined,
  }
}

const loadRows = async () => {
  errorMessage.value = ''
  try {
    const res = await api.attendances({
      search: search.value.trim() || undefined,
      classroom_id: filters.classroomId ?? undefined,
      method: filters.method || undefined,
      // Tanpa pilihan status: tampilkan hadir + terlambat saja (dipisah koma).
      status: filters.status || VISIBLE_STATUSES.join(','),
      page: page.value,
      per_page: PER_PAGE,
    })
    rows.value = res.data.map(toRecord)
    totalRows.value = res.meta.total
    totalPages.value = Math.max(1, res.meta.last_page)
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  }
}

const loadClassrooms = async () => {
  try {
    const res = await api.classrooms()
    classrooms.value = res.data
  } catch {
    // Filter kelas opsional; abaikan bila gagal.
  }
}

/** Pindah halaman, dijaga agar tetap di antara 1 dan halaman terakhir. */
const goToPage = (target: number) => {
  page.value = Math.min(Math.max(1, target), totalPages.value)
}

watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadRows()
  }, 300)
})

watch(filters, () => {
  page.value = 1
  loadRows()
})

watch(page, loadRows)

const fromRow = computed(() => (totalRows.value === 0 ? 0 : (page.value - 1) * PER_PAGE + 1))
const toRow = computed(() => Math.min(page.value * PER_PAGE, totalRows.value))

const emptyText = computed(() =>
  search.value.trim() || filters.classroomId || filters.method || filters.status
    ? 'Tidak ada data yang cocok dengan filter atau pencarianmu.'
    : 'Belum ada data absensi.',
)

/* ---------- Export CSV ---------- */
const exportCsv = () => {
  const header = ['NIS', 'Nama Siswa', 'Kelas', 'Tanggal', 'Jam Masuk', 'Jam Keluar', 'Metode', 'Status', 'Catatan']
  const escape = (value: unknown) => `"${String(value ?? '').replace(/"/g, '""')}"`
  const lines = rows.value.map((row) =>
    [
      row.nis,
      row.name,
      row.kelas,
      row.tanggal,
      row.jamMasuk,
      row.jamKeluar,
      methodLabel(String(row.metode)),
      STATUS_META[row.status as AttendanceStatus].label,
      row.catatan ?? '',
    ]
      .map(escape)
      .join(','),
  )

  const csv = [header.join(','), ...lines].join('\n')
  const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)

  const link = document.createElement('a')
  link.href = url
  link.download = 'data-absensi.csv'
  link.click()
  URL.revokeObjectURL(url)
}

const methodLabel = (value: string) => METHOD_LABEL[value as AttendanceMethod] ?? value

onMounted(async () => {
  await Promise.all([loadRows(), loadClassrooms()])
})

onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer)
})
</script>