<template>
  <!--
    Dashboard ADMIN (Presensi_admin, :3000).
    Fokus: statistik & rekap kehadiran sekolah (analitik/overview).
    Dashboard ini BUKAN milik petugas — panel petugas ada di Presensi_petugas (:3001).
  -->
  <div class="flex flex-col gap-6">
    <p
      v-if="errorMessage"
      class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      role="alert"
    >
      {{ errorMessage }}
    </p>
    <p
      v-if="loading"
      class="rounded-md border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
      role="status"
    >
      Memuat statistik kehadiran terbaru...
    </p>
    <!-- Bento Box Layout -->
    <div class="grid grid-cols-12 gap-4">
      <!-- Top Chart Area - Main Chart (Bento Box) -->
      <base-card
        class="col-span-12"
        title="Statistik Kehadiran"
        subtitle="Tren persentase kehadiran siswa per hari"
      >
        <template #actions>
          <!-- Timeframe Tab (Segmented Control) -->
          <div class="flex items-center overflow-hidden rounded-md border border-gray-200 bg-white">
            <button
              v-for="(option, index) in timeframes"
              :key="option"
              type="button"
              class="px-3 py-1.5 text-xs font-medium transition-colors"
              :class="[
                index > 0 ? 'border-l border-gray-200' : '',
                activeTimeframe === option
                  ? 'bg-emerald-900 text-white'
                  : 'bg-white text-gray-600 hover:bg-slate-50',
              ]"
              @click="activeTimeframe = option"
            >
              {{ option }}
            </button>
          </div>
        </template>

        <!-- Main Chart: tren kehadiran dari data absensi realtime -->
        <line-chart
          class="mt-6"
          :labels="chartData.map((bar) => bar.hari)"
          :series="chartSeries"
          :max-value="100"
          value-suffix="%"
          height-class="h-72 md:h-80"
        />

        <!-- Ringkasan cepat -->
        <div class="mt-6 grid grid-cols-3 divide-x divide-slate-100 border-t border-slate-100 pt-4">
          <div v-for="item in chartSummary" :key="item.label" class="px-3 first:pl-0 last:pr-0">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              {{ item.label }}
            </p>
            <p class="mt-1 text-lg font-semibold tabular-nums tracking-tight text-slate-800">
              {{ item.value }}
            </p>
            <p class="mt-0.5 text-[11px] text-slate-400">{{ item.hint }}</p>
          </div>
        </div>
      </base-card>

      <!-- Kiri: Perubahan Kehadiran -->
      <base-card
        class="col-span-12 md:col-span-4"
        title="Perubahan Kehadiran"
        subtitle="Perbandingan harian kehadiran"
      >
        <div class="flex h-32 items-end justify-between gap-2">
          <div
            v-for="(item, index) in perubahanKehadiran"
            :key="index"
            class="group relative flex flex-1 flex-col items-center justify-end gap-1"
          >
            <div
              class="w-full rounded-sm transition-all duration-300"
              :class="item.type === 'up' ? 'bg-emerald-900' : 'bg-red-500'"
              :style="{ height: `${item.value}%` }"
            />
          </div>
        </div>
      </base-card>

      <!-- Tengah: Komposisi Kehadiran -->
      <base-card
        class="col-span-12 md:col-span-4"
        title="Komposisi Kehadiran"
        subtitle="Persentase status hari ini"
      >
        <div class="flex flex-col gap-3">
          <div
            v-for="item in komposisiKehadiran"
            :key="item.label"
            class="flex flex-col gap-1"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs text-gray-500">{{ item.label }}</span>
              <span class="text-xs font-medium text-slate-800">{{ item.value }}%</span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-slate-100">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="komposisiTone(item.label)"
                :style="{ width: `${item.value}%` }"
              />
            </div>
          </div>
        </div>
      </base-card>

      <!-- Kanan: Ringkasan Sekolah -->
      <base-card
        class="col-span-12 md:col-span-4"
        title="Ringkasan Sekolah"
        subtitle="Data rekap kehadiran terkini"
      >
        <div class="flex flex-col gap-3">
          <div
            v-for="item in ringkasanSekolah"
            :key="item.label"
            class="flex items-center justify-between border-b border-gray-100 pb-2 last:border-0 last:pb-0"
          >
            <span class="text-xs text-gray-500">{{ item.label }}</span>
            <span
              class="text-xs font-medium"
              :class="item.accent ? 'text-emerald-700' : 'text-slate-800'"
            >
              {{ item.value }}
            </span>
          </div>
        </div>
      </base-card>
    </div>

    <!-- Stats Grid Bento Style -->
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
      <base-card v-for="stat in stats" :key="stat.label">
        <div class="flex justify-between">
          <!-- Sisi Kiri -->
          <div class="flex flex-col">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
              {{ stat.label }}
            </p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-800">
              {{ stat.value }}
            </p>
          </div>
          <!-- Sisi Kanan (Ikon) -->
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-white"
          >
            <component :is="stat.icon" class="h-5 w-5" />
          </div>
        </div>
        <div class="mt-3 flex items-center">
          <span class="text-xs font-medium" :class="stat.tone">
            {{ stat.trend }}
          </span>
        </div>
      </base-card>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ClipboardList, UserCheck, UserX, Users } from '@lucide/vue'
import type { LineChartSeries } from '#layers/shared/app/components/ui/LineChart.vue'
import type { ApiAttendance } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'default',
  title: 'Dashboard',
})

const api = useApi()

const activeTimeframe = ref('Week')
const loading = ref(false)
const errorMessage = ref('')
const totalStudents = ref(0)
const todayAttendances = ref<ApiAttendance[]>([])
const attendanceHistory = ref<ApiAttendance[]>([])

const timeframes = ['Day', 'Week', 'Month', 'Year']

type ChartPoint = { hari: string; date: string; hadir: number }

const DAY_LABELS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

const toIsoDate = (date: Date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

const formatDateLabel = (date: Date, timeframe: string) => {
  if (timeframe === 'Year') return new Intl.DateTimeFormat('id-ID', { month: 'short' }).format(date)
  if (timeframe === 'Day') return 'Hari ini'
  return DAY_LABELS[date.getDay()] ?? ''
}

const timeframeDays = computed(() => {
  if (activeTimeframe.value === 'Day') return 1
  if (activeTimeframe.value === 'Month') return 30
  if (activeTimeframe.value === 'Year') return 365
  return 7
})

const chartData = computed<ChartPoint[]>(() => {
  const days: ChartPoint[] = []
  const end = new Date()
  const count = timeframeDays.value
  const step = activeTimeframe.value === 'Year' ? 30 : 1
  const points = activeTimeframe.value === 'Year' ? 12 : count

  for (let index = points - 1; index >= 0; index -= 1) {
    const date = new Date(end)
    date.setDate(end.getDate() - index * step)
    const iso = toIsoDate(date)
    const records = attendanceHistory.value.filter(
      (attendance) => attendance.attendance_date === iso && attendance.session.code === 'masuk',
    )
    const present = records.filter((attendance) => ['hadir', 'terlambat'].includes(attendance.status.code)).length

    days.push({
      hari: formatDateLabel(date, activeTimeframe.value),
      date: iso,
      hadir: totalStudents.value ? Math.round((present / totalStudents.value) * 100) : 0,
    })
  }

  return days
})

const chartSeries = computed<LineChartSeries[]>(() => [
  {
    label: 'Hadir',
    color: '#064e3b',
    area: true,
    values: chartData.value.map((bar) => bar.hadir),
  },
])

const formatPercent = (value: number) => {
  const formatted = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value)
  return `${formatted}%`
}

const chartSummary = computed(() => {
  const values = chartData.value.map((bar) => bar.hadir)
  const average = values.reduce((total, value) => total + value, 0) / Math.max(1, values.length)
  const highest = chartData.value.reduce((best, item) => (item.hadir > best.hadir ? item : best), { hari: '-', date: '', hadir: 0 })
  const lowest = chartData.value.reduce((worst, item) => (item.hadir < worst.hadir ? item : worst), { hari: '-', date: '', hadir: 0 })

  return [
    { label: 'Rata-rata', value: formatPercent(average), hint: 'kehadiran minggu ini' },
    { label: 'Tertinggi', value: formatPercent(highest.hadir), hint: highest.hari },
    { label: 'Terendah', value: formatPercent(lowest.hadir), hint: lowest.hari },
  ]
})

const presentToday = computed(() =>
  todayAttendances.value.filter((attendance) => ['hadir', 'terlambat'].includes(attendance.status.code)).length,
)
const izinSakitToday = computed(() =>
  todayAttendances.value.filter((attendance) => ['izin', 'sakit'].includes(attendance.status.code)).length,
)
const alfaToday = computed(() => Math.max(0, totalStudents.value - presentToday.value - izinSakitToday.value))
const attendancePercent = computed(() =>
  totalStudents.value ? Math.round((presentToday.value / totalStudents.value) * 100) : 0,
)

const stats = computed(() => [
  { label: 'Total Siswa', value: String(totalStudents.value), trend: 'data realtime', tone: 'text-emerald-700', icon: Users },
  { label: 'Hadir Hari Ini', value: String(presentToday.value), trend: `${attendancePercent.value}% dari total siswa`, tone: 'text-emerald-700', icon: UserCheck },
  { label: 'Izin / Sakit', value: String(izinSakitToday.value), trend: 'tercatat hari ini', tone: 'text-amber-600', icon: ClipboardList },
  { label: 'Alfa', value: String(alfaToday.value), trend: 'belum absen hari ini', tone: 'text-red-600', icon: UserX },
])

const perubahanKehadiran = computed(() => chartData.value.slice(-7).map((item, index, values) => ({
  type: index === 0 || item.hadir >= (values[index - 1]?.hadir ?? item.hadir) ? 'up' : 'down',
  value: item.hadir,
})))

const komposisiKehadiran = computed(() => [
  { label: 'Hadir', value: attendancePercent.value },
  { label: 'Izin / Sakit', value: totalStudents.value ? Math.round((izinSakitToday.value / totalStudents.value) * 100) : 0 },
  { label: 'Alfa', value: totalStudents.value ? Math.round((alfaToday.value / totalStudents.value) * 100) : 0 },
])

/** Warna progress per status kehadiran. */
const komposisiTone = (label: string) => {
  const map: Record<string, string> = {
    Hadir: 'bg-emerald-900',
    'Izin / Sakit': 'bg-amber-500',
    Alfa: 'bg-red-500',
  }
  return map[label] ?? 'bg-emerald-900'
}

const ringkasanSekolah = computed(() => [
  { label: 'Rata-rata Kehadiran', value: formatPercent(chartData.value.reduce((sum, item) => sum + item.hadir, 0) / Math.max(1, chartData.value.length)), accent: true },
  { label: 'Total Izin / Sakit Hari Ini', value: `${izinSakitToday.value} Siswa`, accent: false },
  { label: 'Alfa Hari Ini', value: `${alfaToday.value} Siswa`, accent: false },
  { label: 'Rentang Grafik', value: activeTimeframe.value, accent: false },
])

const loadDashboard = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const today = toIsoDate(new Date())
    const [students, todayResponse, historyResponse] = await Promise.all([
      api.students({ status: 'aktif', per_page: 1 }),
      api.attendances({ date: today, session: 'masuk', per_page: 200 }),
      api.attendances({ per_page: 200 }),
    ])

    totalStudents.value = students.meta.total
    todayAttendances.value = todayResponse.data
    attendanceHistory.value = historyResponse.data
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>
