<template>
  <div class="flex flex-col gap-6">
    <!-- Filter -->
    <base-card
      title="Filter Rekap"
      subtitle="Pilih tanggal dan kelas untuk menampilkan rekap kehadiran."
    >
      <!-- Export selalu tampil di menu Rekap (satu tombol, format Excel). -->
      <template #actions>
        <base-button
          variant="solid"
          data-testid="export-xlsx"
          @click="exportExcel"
        >
          <Download class="h-4 w-4" />
          Export
        </base-button>
      </template>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Tanggal</span>
          <input
            v-model="selectedDate"
            type="date"
            data-testid="recap-date"
            class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 transition-colors focus:border-emerald-900 focus:outline-none"
          >
        </label>

        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Tahun Ajaran</span>
          <select
            v-model="selectedYearId"
            data-testid="recap-year"
            class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 transition-colors focus:border-emerald-900 focus:outline-none"
          >
            <option :value="null">Semua Tahun Ajaran</option>
            <option v-for="year in academicYears" :key="year.id" :value="year.id">
              {{ year.label }}<template v-if="year.is_active"> (Aktif)</template>
            </option>
          </select>
        </label>

        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Jurusan</span>
          <select
            v-model="selectedMajorId"
            data-testid="recap-major"
            class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 transition-colors focus:border-emerald-900 focus:outline-none"
          >
            <option :value="null">Pilih Jurusan</option>
            <option v-for="major in majorOptions" :key="major.id" :value="major.id">
              {{ major.code }} — {{ major.name }}
            </option>
          </select>
        </label>

        <label class="flex flex-col gap-2">
          <span class="text-sm font-medium text-slate-800">Kelas</span>
          <select
            v-model="selectedClassroomId"
            data-testid="recap-classroom"
            :disabled="!selectedMajorId"
            class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 transition-colors focus:border-emerald-900 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-gray-400"
          >
            <option :value="null">Pilih Kelas</option>
            <option v-for="room in classroomOptions" :key="room.id" :value="room.id">
              {{ room.name }}
            </option>
          </select>
        </label>
      </div>
    </base-card>

    <p
      v-if="errorMessage"
      class="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      role="alert"
    >
      <TriangleAlert class="h-4 w-4 shrink-0" />
      {{ errorMessage }}
    </p>

    <!-- Empty state: kelas belum dipilih -->
    <base-card v-if="!selectedClassroomId">
      <div class="flex flex-col items-center gap-3 py-10 text-center">
        <FolderOpen class="h-10 w-10 text-gray-300" />
        <p class="text-sm font-medium text-slate-700">Pilih kelas untuk melihat rekap</p>
        <p class="max-w-md text-sm text-gray-500">
          Rekap menampilkan seluruh siswa aktif pada kelas tersebut. Siswa yang belum absen
          otomatis berstatus <span class="font-medium text-slate-700">Alpa (belum absen)</span>.
        </p>
      </div>
    </base-card>

    <template v-else>
      <!-- Ringkasan -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div
          v-for="card in summaryCards"
          :key="card.key"
          class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm"
        >
          <p class="text-xs font-medium text-gray-500">{{ card.label }}</p>
          <p class="mt-1 text-2xl font-semibold" :class="card.valueClass">{{ card.value }}</p>
        </div>
      </div>

      <!-- Toolbar tabel -->
      <div>
        <h3 class="text-base font-semibold text-slate-800">
          {{ recap?.classroom?.name ?? 'Kelas' }}
        </h3>
        <p class="text-sm text-gray-500">{{ longDate }}</p>
      </div>

      <!-- Tabel rekap -->
      <data-table
        :columns="columns"
        :rows="rows"
        :empty-text="isLoading ? 'Memuat data...' : 'Belum ada siswa pada kelas ini.'"
      >
        <template #cell-status="{ value }">
          <span
            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="STATUS_META[value as AttendanceStatus].badge"
          >
            {{ statusLabel(value as AttendanceStatus) }}
          </span>
        </template>
        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-2">
            <button
              v-if="row.attendanceId"
              type="button"
              class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-emerald-900 text-white transition-colors hover:bg-emerald-800 focus:outline-none"
              :aria-label="`Edit ${String(row.name)}`"
              title="Edit status / catatan"
              data-testid="edit-row"
              @click="openEdit(row)"
            >
              <Pencil class="h-4 w-4" />
            </button>
            <div v-if="row.waHref" class="flex flex-col items-end gap-1">
              <a
                :href="String(row.waHref)"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 rounded-md bg-emerald-900 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-emerald-800"
                :title="`Kirim WhatsApp ke ${String(row.guardianName)}`"
                data-testid="wa-wali"
              >
                <MessageCircle class="h-3.5 w-3.5" />
                WhatsApp
              </a>
              <span class="text-[11px] text-gray-400">{{ String(row.guardianMaskedPhone) }}</span>
            </div>
            <span
              v-else-if="row.needsNotice"
              class="text-xs text-gray-400"
            >
              Kontak wali belum tersedia
            </span>
            <span v-else class="text-xs text-gray-300">—</span>
          </div>
        </template>
      </data-table>

      <!-- Modal edit status / catatan (pindahan dari Data Absensi) -->
      <edit-attendance-modal v-model="isEditOpen" :row="editingRow" @saved="handleSaved" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { Download, FolderOpen, MessageCircle, Pencil, TriangleAlert } from '@lucide/vue'
import type { TableColumn } from '#layers/shared/app/components/table/DataTable.vue'
import type {
  ApiAcademicYear,
  ApiAttendanceRecap,
  ApiClassroom,
  ApiGuardian,
} from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'petugas',
  title: 'Rekap Absensi',
})

const api = useApi()

/** Status yang layak dinotifikasi ke wali (hadir tidak perlu dihubungi). */
const NOTICE_STATUSES: AttendanceStatus[] = ['terlambat', 'izin', 'sakit', 'alpa']

const STATUS_LABEL_OVERRIDE: Partial<Record<AttendanceStatus, string>> = {
  alpa: 'Alpa (belum absen)',
}

const statusLabel = (code: AttendanceStatus) =>
  STATUS_LABEL_OVERRIDE[code] ?? STATUS_META[code]?.label ?? code

/* ---------- Sumber referensi ---------- */
const classrooms = ref<ApiClassroom[]>([])
const academicYears = ref<ApiAcademicYear[]>([])

/* ---------- Filter ---------- */
const todayIso = () => new Date().toISOString().slice(0, 10)

const selectedDate = ref(todayIso())
const selectedYearId = ref<number | null>(null)
const selectedMajorId = ref<number | null>(null)
const selectedClassroomId = ref<number | null>(null)

const majorOptions = computed(() => {
  const map = new Map<number, { id: number; code: string; name: string }>()
  for (const room of classrooms.value) {
    if (room.major) map.set(room.major.id, room.major)
  }
  return [...map.values()].sort((a, b) => a.code.localeCompare(b.code))
})

const classroomOptions = computed(() =>
  classrooms.value.filter(
    (room) => selectedMajorId.value !== null && room.major?.id === selectedMajorId.value,
  ),
)

/* ---------- Rekap ---------- */
const recap = ref<ApiAttendanceRecap | null>(null)
const isLoading = ref(false)
const errorMessage = ref('')

const longDate = computed(() =>
  new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(
    new Date(`${selectedDate.value}T00:00:00`),
  ),
)

const formatTime = (value: string | null) =>
  value
    ? new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
    : '-'

/** Sensor nomor HP wali di tampilan (etika privasi). */
const maskPhone = (phone: string) => {
  const digits = phone.replace(/\D/g, '')
  if (digits.length <= 6) return '*'.repeat(digits.length)
  return `${digits.slice(0, 4)}${'*'.repeat(digits.length - 6)}${digits.slice(-2)}`
}

/** Ubah 08xx menjadi 628xx agar valid untuk tautan wa.me. */
const normalizePhone = (phone: string) => {
  const digits = phone.replace(/\D/g, '')
  if (digits.startsWith('0')) return `62${digits.slice(1)}`
  return digits
}

const noticeText = (name: string, code: AttendanceStatus) =>
  `Halo Bapak/Ibu wali dari ${name}, kami menginformasikan bahwa pada ${longDate.value} ` +
  ` siswa tersebut tercatat *${statusLabel(code)}* di ${recap.value?.classroom?.name ?? 'kelas'}. ` +
  'Mohon konfirmasinya. Terima kasih.'

const buildHref = (name: string, code: AttendanceStatus, guardian: ApiGuardian) =>
  `https://wa.me/${normalizePhone(guardian.phone)}?text=${encodeURIComponent(noticeText(name, code))}`

/* ---------- Baris tabel ---------- */
const columns: TableColumn[] = [
  { key: 'nis', label: 'NIS' },
  { key: 'name', label: 'Nama Siswa' },
  { key: 'status', label: 'Status' },
  { key: 'waktu', label: 'Waktu Absen' },
  { key: 'actions', label: 'Aksi', align: 'right' },
]

const rows = computed<Record<string, unknown>[]>(() =>
  (recap.value?.students ?? []).map((student) => {
    const needsNotice = NOTICE_STATUSES.includes(student.status.code)
    const guardian = student.guardian

    return {
      id: student.id,
      attendanceId: student.attendance_id,
      nis: student.nis,
      name: student.full_name,
      status: student.status.code,
      waktu: formatTime(student.checked_at),
      catatan: student.note,
      needsNotice,
      guardianName: guardian?.name ?? '',
      guardianMaskedPhone: guardian ? maskPhone(guardian.phone) : '',
      waHref: needsNotice && guardian ? buildHref(student.full_name, student.status.code, guardian) : '',
    }
  }),
)

const summaryCards = computed(() => {
  const summary = recap.value?.summary
  return [
    { key: 'total', label: 'Total Siswa', value: summary?.total ?? 0, valueClass: 'text-slate-800' },
    { key: 'hadir', label: 'Hadir', value: summary?.hadir ?? 0, valueClass: 'text-emerald-700' },
    { key: 'terlambat', label: 'Terlambat', value: summary?.terlambat ?? 0, valueClass: 'text-amber-600' },
    { key: 'izin', label: 'Izin', value: summary?.izin ?? 0, valueClass: 'text-sky-700' },
    { key: 'sakit', label: 'Sakit', value: summary?.sakit ?? 0, valueClass: 'text-slate-600' },
    { key: 'alpa', label: 'Alpa', value: summary?.alpa ?? 0, valueClass: 'text-red-600' },
  ]
})

/* ---------- Aksi ---------- */
const loadRecap = async () => {
  if (!selectedClassroomId.value) {
    recap.value = null
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    const res = await api.attendanceRecap({
      classroom_id: selectedClassroomId.value,
      date: selectedDate.value,
      academic_year_id: selectedYearId.value,
    })
    recap.value = res.data
  } catch (error) {
    recap.value = null
    errorMessage.value = apiErrorMessage(error)
  } finally {
    isLoading.value = false
  }
}

const exportExcel = async () => {
  if (!recap.value || !rows.value.length) return

  // Impor dinamis supaya xlsx tidak ikut bundle SSR.
  const XLSX = await import('xlsx')

  const data = recap.value.students.map((student) => ({
    NIS: student.nis,
    'Nama Siswa': student.full_name,
    Kelas: recap.value?.classroom?.name ?? '',
    Tanggal: selectedDate.value,
    Status: statusLabel(student.status.code),
    'Waktu Absen': formatTime(student.checked_at),
  }))

  const sheet = XLSX.utils.json_to_sheet(data)
  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, sheet, 'Rekap')

  const slug = (recap.value.classroom?.name ?? 'kelas').replace(/\s+/g, '-').toLowerCase()
  XLSX.writeFile(workbook, `rekap-${slug}-${selectedDate.value}.xlsx`)
}

/* ---------- Edit status / catatan (pindahan dari Data Absensi) ---------- */
const isEditOpen = ref(false)
const editingRow = ref<AttendanceRecord | null>(null)

const openEdit = (row: Record<string, unknown>) => {
  editingRow.value = {
    id: Number(row.attendanceId),
    nis: String(row.nis),
    name: String(row.name),
    kelas: recap.value?.classroom?.name ?? '-',
    tanggal: longDate.value,
    jamMasuk: String(row.waktu),
    jamKeluar: '--:--',
    metode: 'face recognition',
    status: row.status as AttendanceStatus,
    catatan: (row.catatan as string | null) ?? undefined,
  }
  isEditOpen.value = true
}

const handleSaved = async (updated: AttendanceRecord) => {
  try {
    await api.updateAttendance(updated.id, {
      status: updated.status,
      note: updated.catatan ?? null,
    })
    isEditOpen.value = false
    await loadRecap()
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  }
}

/* ---------- Watch ---------- */
watch(selectedMajorId, () => {
  selectedClassroomId.value = null
})

watch([selectedClassroomId, selectedDate, selectedYearId], loadRecap)

onMounted(async () => {
  try {
    const [roomRes, yearRes] = await Promise.all([api.classrooms(), api.academicYears()])
    classrooms.value = roomRes.data
    academicYears.value = yearRes.data
    selectedYearId.value = yearRes.data.find((year) => year.is_active)?.id ?? null
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  }
})
</script>
