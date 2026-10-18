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
          <base-button variant="solid" data-testid="export-csv" @click="exportCsv">
            <Download class="h-4 w-4" />
            Export
          </base-button>
        </div>
      </div>

      <!-- Filter dropdown -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Kelas</span>
          <select
            v-model="filters.classroomId"
            class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 transition-colors focus:border-emerald-900 focus:outline-none"
          >
            <option :value="null">Semua Kelas</option>
            <option v-for="room in classrooms" :key="room.id" :value="room.id">
              {{ room.name }}
            </option>
          </select>
        </label>

        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Metode Absensi</span>
          <select
            v-model="filters.method"
            class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 transition-colors focus:border-emerald-900 focus:outline-none"
          >
            <option value="">Semua Metode</option>
            <option v-for="opt in methodOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </label>

        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Status</span>
          <select
            v-model="filters.status"
            class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 transition-colors focus:border-emerald-900 focus:outline-none"
          >
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
    <div class="flex flex-col gap-3">
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
        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end">
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-emerald-900 text-white transition-colors hover:bg-emerald-800 focus:outline-none"
              :aria-label="`Edit ${String(row.name)}`"
              data-testid="edit-row"
              @click="openEdit(row)"
            >
              <Pencil class="h-4 w-4" />
            </button>
          </div>
        </template>
      </data-table>

      <!-- Pagination -->
      <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-xs text-gray-500">
          Menampilkan {{ fromRow }}–{{ toRow }} dari {{ totalRows }} data
        </p>
        <pagination v-model:current-page="page" :total-pages="totalPages" />
      </div>
    </div>

    <!-- Modal Edit Data Absensi -->
    <edit-attendance-modal v-model="isEditOpen" :row="editingRow" @saved="handleSaved" />
  </div>
</template>

<script setup lang="ts">
import { Download, Pencil, Search, TriangleAlert } from '@lucide/vue'
import type { TableColumn } from '#layers/shared/app/components/table/DataTable.vue'
import type { ApiAttendance, ApiClassroom } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'petugas',
  title: 'Data Absensi',
})

const api = useApi()

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

const statusOptions = ATTENDANCE_STATUSES.map((value) => ({
  value,
  label: STATUS_META[value].label,
}))

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
  { key: 'actions', label: 'Aksi', align: 'right' },
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
    actions: '',
  }
}

const loadRows = async () => {
  errorMessage.value = ''
  try {
    const res = await api.attendances({
      search: search.value.trim() || undefined,
      classroom_id: filters.classroomId ?? undefined,
      method: filters.method || undefined,
      status: filters.status || undefined,
      page: page.value,
      per_page: PER_PAGE,
    })
    rows.value = res.data.map(toRecord)
    totalRows.value = res.meta.total
    totalPages.value = res.meta.last_page
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

/* ---------- Modal edit ---------- */
const isEditOpen = ref(false)
const editingRow = ref<AttendanceRecord | null>(null)

const openEdit = (row: Record<string, unknown>) => {
  editingRow.value = {
    id: Number(row.id),
    nis: String(row.nis),
    name: String(row.name),
    kelas: String(row.kelas),
    tanggal: String(row.tanggal),
    jamMasuk: String(row.jamMasuk),
    jamKeluar: String(row.jamKeluar),
    metode: row.metode as AttendanceMethod,
    status: row.status as AttendanceStatus,
    catatan: row.catatan as string | undefined,
  }
  isEditOpen.value = true
}

const handleSaved = async (updated: AttendanceRecord) => {
  try {
    await api.updateAttendance(updated.id, {
      status: updated.status,
      note: updated.catatan ?? null,
    })
    rows.value = rows.value.map((row) =>
      row.id === updated.id
        ? { ...row, status: updated.status, catatan: updated.catatan }
        : row,
    )
    isEditOpen.value = false
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  }
}

const methodLabel = (value: string) => METHOD_LABEL[value as AttendanceMethod] ?? value

onMounted(async () => {
  await Promise.all([loadRows(), loadClassrooms()])
})

onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer)
})
</script>
