<template>
  <!--
    Dashboard PETUGAS (Presensi_petugas, :3001).
    Fokus: monitoring kehadiran harian (total siswa, hadir, tidak hadir,
    absensi terbaru, jadwal piket).
    Dashboard ini BUKAN milik admin — dashboard admin ada di Presensi_admin (:3000).
  -->
  <div class="flex flex-col gap-6">
    <p
      v-if="errorMessage"
      class="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      role="alert"
    >
      <TriangleAlert class="h-4 w-4 shrink-0" />
      {{ errorMessage }}
    </p>

    <!-- Filter tahun ajaran -->
    <div class="flex flex-wrap items-center justify-end gap-2">
      <label for="dashboard-year" class="text-sm font-medium text-slate-700">Tahun Ajaran</label>
      <select
        id="dashboard-year"
        v-model="selectedYearId"
        class="rounded-md border bg-white px-3 py-2 text-sm text-slate-800 transition-colors focus:border-emerald-900 focus:outline-none"
        :disabled="loadingYears"
      >
        <option :value="null">Semua Tahun Ajaran</option>
        <option v-for="year in academicYears" :key="year.id" :value="year.id">
          {{ year.label }}<template v-if="year.is_active"> (Aktif)</template>
        </option>
      </select>
    </div>

    <!-- Stat Cards -->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <!-- Kartu 1: Total Siswa -->
      <base-card>
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Siswa</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-slate-800">{{ totalStudents }}</p>
            <p class="mt-2 text-xs text-gray-500">{{ classroomCount }} kelas</p>
          </div>
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-white"
          >
            <Users class="h-5 w-5" />
          </div>
        </div>
      </base-card>

      <!-- Kartu 2: Hadir -->
      <base-card>
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Hadir</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-slate-800">{{ hadirToday }}</p>
            <p class="mt-2 text-xs text-gray-500">
              Batas absen masuk {{ boundaryTime ?? '--:--' }}
            </p>
          </div>
          <div class="flex flex-col items-end gap-2">
            <span
              class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800"
            >
              {{ hadirPersen }}%
            </span>
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-white"
            >
              <UserCheck class="h-5 w-5" />
            </div>
          </div>
        </div>
      </base-card>

      <!-- Kartu 3: Tidak Hadir -->
      <base-card>
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Tidak Hadir</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-slate-800">{{ tidakHadir }}</p>
            <p class="mt-2 text-xs text-gray-500">Belum tercatat absen masuk hari ini</p>
          </div>
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-white"
          >
            <UserX class="h-5 w-5" />
          </div>
        </div>
      </base-card>

      <!-- Kartu 4: Persentase Kehadiran -->
      <base-card>
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
              Persentase Kehadiran
            </p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-slate-800">{{ persentase }}%</p>
            <p class="mt-2 text-xs text-gray-500">Dihitung dari siswa yang sudah absen masuk</p>
          </div>
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-white"
          >
            <Activity class="h-5 w-5" />
          </div>
        </div>
      </base-card>
    </section>

    <!-- Bagian Tengah: Grafik aktivitas absensi + jadwal piket -->
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
      <base-card
        class="lg:col-span-2"
        title="Statistik Kehadiran"
        subtitle="Tren persentase kehadiran siswa per hari"
      >
        <template #actions>
          <div class="flex items-center gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1.5">
              <span class="h-0.5 w-4 rounded-full bg-emerald-900" />
              Hadir
              <span class="font-semibold tabular-nums text-slate-700">{{ chartSummary.average }}%</span>
            </span>
          </div>
        </template>

        <div v-if="hasChartData" class="mt-4">
          <line-chart
            :labels="chartDays.map((day) => day.day)"
            :series="chartSeries"
            :max-value="100"
            value-suffix="%"
            height-class="h-72 md:h-80"
          />
        </div>

        <div v-else class="mt-4 flex h-56 items-center justify-center text-sm text-gray-500">
          Belum ada data absensi minggu ini.
        </div>
      </base-card>

      <base-card title="Jadwal Piket" subtitle="Petugas dan lokasi hari ini">
        <div class="mt-4 flex h-56 items-center justify-center text-center text-sm text-gray-500">
          Belum ada jadwal piket.
        </div>
      </base-card>
    </section>

    <!-- Bagian Bawah: Tabel Absensi Terbaru -->
    <base-card title="Absensi Terbaru" subtitle="Log kehadiran siswa paling baru">
      <data-table :columns="recentColumns" :rows="recentRows" :empty-text="'Belum ada data absensi.'">
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
    </base-card>
  </div>
</template>

<script setup lang="ts">
import { Activity, TriangleAlert, UserCheck, Users, UserX } from '@lucide/vue'
import type { TableColumn } from '#layers/shared/app/components/table/DataTable.vue'
import type { LineChartSeries } from '#layers/shared/app/components/ui/LineChart.vue'
import type { ApiAcademicYear, ApiAttendance } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'petugas',
  title: 'Dashboard',
})

const api = useApi()

const academicYears = ref<ApiAcademicYear[]>([])
const selectedYearId = ref<number | null>(null)
const loadingYears = ref(false)

const totalStudents = ref(0)
const classroomCount = ref(0)
const hadirToday = ref(0)
const terlambatToday = ref(0)
const boundaryTime = ref<string | null>(null)
const errorMessage = ref('')
const recentRows = ref<Record<string, unknown>[]>([])
const chartDays = ref<Array<{ day: string; hadir: number }>>([])

const recentColumns: TableColumn[] = [
  { key: 'name', label: 'Nama' },
  { key: 'kelas', label: 'Kelas' },
  { key: 'jamMasuk', label: 'Jam Masuk' },
  { key: 'metode', label: 'Metode' },
  { key: 'status', label: 'Status' },
]

const DAY_LABELS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

const formatTime = (value: string) =>
  new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))

const toIsoDate = (date: Date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

const methodLabel = (value: string) => METHOD_LABEL[value as AttendanceMethod] ?? value

const toRecentRow = (attendance: ApiAttendance) => {
  const isMasuk = attendance.session.code === 'masuk'

  return {
    name: attendance.student?.full_name ?? '-',
    kelas: attendance.student?.classroom?.name ?? '-',
    jamMasuk: isMasuk ? formatTime(attendance.checked_at) : '--:--',
    metode: attendance.method.code.replace('_', ' '),
    status: attendance.status.code,
  }
}

/** Bangun data grafik 7 hari terakhir dari daftar absensi (sesi masuk). */
const buildChart = (attendances: ApiAttendance[]) => {
  const days: Array<{ day: string; hadir: number }> = []

  for (let offset = 6; offset >= 0; offset--) {
    const date = new Date()
    date.setDate(date.getDate() - offset)
    const iso = toIsoDate(date)
    const masuk = attendances.filter(
      (attendance) => attendance.attendance_date === iso && attendance.session.code === 'masuk',
    )

    const present = masuk.filter((attendance) => ['hadir', 'terlambat'].includes(attendance.status.code)).length
    days.push({
      day: DAY_LABELS[date.getDay()] ?? '',
      hadir: totalStudents.value ? Math.round((present / totalStudents.value) * 100) : 0,
    })
  }

  chartDays.value = days
}

const chartSeries = computed<LineChartSeries[]>(() => [
  {
    label: 'Hadir',
    color: '#064e3b',
    area: true,
    values: chartDays.value.map((day) => day.hadir),
  },
])

const chartSummary = computed(() => ({
  average: Math.round(
    chartDays.value.reduce((total, day) => total + day.hadir, 0) / Math.max(1, chartDays.value.length),
  ),
}))

const hasChartData = computed(() => chartDays.value.some((day) => day.hadir))

const tidakHadir = computed(() =>
  Math.max(0, totalStudents.value - hadirToday.value - terlambatToday.value),
)
const kehadiranCount = computed(() => hadirToday.value + terlambatToday.value)
const persentase = computed(() =>
  totalStudents.value ? Math.round((kehadiranCount.value / totalStudents.value) * 100) : 0,
)
const hadirPersen = computed(() =>
  totalStudents.value ? Math.round((hadirToday.value / totalStudents.value) * 100) : 0,
)

const loadDashboard = async () => {
  errorMessage.value = ''
  const today = toIsoDate(new Date())

  try {
    const [students, classrooms, meta, hadir, terlambat, recent] = await Promise.all([
      api.students({
        status: 'aktif',
        academic_year_id: selectedYearId.value ?? undefined,
        per_page: 1,
      }),
      api.classrooms(),
      api.meta(),
      api.attendances({
        date: today,
        session: 'masuk',
        status: 'hadir',
        academic_year_id: selectedYearId.value ?? undefined,
        per_page: 1,
      }),
      api.attendances({
        date: today,
        session: 'masuk',
        status: 'terlambat',
        academic_year_id: selectedYearId.value ?? undefined,
        per_page: 1,
      }),
      api.attendances({
        academic_year_id: selectedYearId.value ?? undefined,
        per_page: 200,
      }),
    ])

    totalStudents.value = students.meta.total
    classroomCount.value = classrooms.data.length
    hadirToday.value = hadir.meta.total
    terlambatToday.value = terlambat.meta.total

    const masuk = meta.data.attendance_sessions.find((session) => session.code === 'masuk')
    boundaryTime.value = masuk?.boundary_time ? masuk.boundary_time.slice(0, 5) : null

    recentRows.value = recent.data.slice(0, 5).map(toRecentRow)
    buildChart(recent.data)
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  }
}

const loadAcademicYears = async () => {
  loadingYears.value = true
  try {
    const response = await api.academicYears()
    academicYears.value = response.data

    // Default ke tahun ajaran aktif; petugas tetap bisa memilih tahun lain.
    const active = response.data.find((year) => year.is_active)
    if (active) selectedYearId.value = active.id
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  } finally {
    loadingYears.value = false
  }
}

watch(selectedYearId, loadDashboard)

onMounted(async () => {
  await loadAcademicYears()
  await loadDashboard()
})
</script>
