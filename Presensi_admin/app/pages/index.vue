<template>
  <!--
    Dashboard ADMIN (Presensi_admin, :3000).
    Fokus: statistik & rekap kehadiran sekolah (analitik/overview).
    Dashboard ini BUKAN milik petugas — panel petugas ada di Presensi_petugas (:3001).
  -->
  <div class="flex flex-col gap-6">
    <!-- Bento Box Layout -->
    <div class="grid grid-cols-12 gap-4">
      <!-- Top Chart Area - Main Chart (Bento Box) -->
      <base-card
        class="col-span-12"
        title="Statistik Kehadiran"
        subtitle="Perbandingan kehadiran siswa per hari"
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

        <!-- Main Chart (Dummy) -->
        <div class="mt-6 flex h-72 flex-col justify-between gap-6 md:h-80">
          <div class="relative flex h-full items-end justify-between gap-3 px-2 sm:gap-4">
            <div
              v-for="(bar, index) in chartData"
              :key="index"
              class="group relative flex flex-1 flex-col items-center justify-end gap-2"
            >
              <div class="flex w-full items-end gap-0.5">
                <!-- Pilar Kehadiran -->
                <div
                  class="w-full rounded-sm bg-emerald-900 transition-all duration-300"
                  :style="{ height: `${bar.hadir}%` }"
                />
              </div>
            </div>
          </div>
          <!-- Label Hari -->
          <div class="flex items-center justify-between gap-3 px-2 sm:gap-4">
            <span
              v-for="(bar, index) in chartData"
              :key="`label-${index}`"
              class="flex-1 text-center text-xs text-gray-500"
            >
              {{ bar.hari }}
            </span>
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

definePageMeta({
  layout: 'default',
  title: 'Dashboard',
})

const activeTimeframe = ref('Month')

const timeframes = ['Day', 'Week', 'Month', 'Year']

const stats = [
  { label: 'Total Siswa', value: '245', trend: '+2% dari kemarin', tone: 'text-emerald-700', icon: Users },
  { label: 'Hadir Hari Ini', value: '218', trend: '+5% dari kemarin', tone: 'text-emerald-700', icon: UserCheck },
  { label: 'Izin / Sakit', value: '18', trend: '+1% dari kemarin', tone: 'text-amber-600', icon: ClipboardList },
  { label: 'Alfa', value: '9', trend: '-2% dari kemarin', tone: 'text-red-600', icon: UserX },
]

const chartData = [
  { hari: 'Senin', hadir: 85 },
  { hari: 'Selasa', hadir: 92 },
  { hari: 'Rabu', hadir: 88 },
  { hari: 'Kamis', hadir: 94 },
  { hari: 'Jumat', hadir: 90 },
  { hari: 'Sabtu', hadir: 78 },
  { hari: 'Minggu', hadir: 65 },
]

const perubahanKehadiran = [
  { type: 'up', value: 70 },
  { type: 'up', value: 85 },
  { type: 'down', value: 40 },
  { type: 'up', value: 90 },
  { type: 'down', value: 35 },
  { type: 'up', value: 75 },
]

const komposisiKehadiran = [
  { label: 'Hadir', value: 89 },
  { label: 'Izin / Sakit', value: 7 },
  { label: 'Alfa', value: 4 },
]

/** Warna progress per status kehadiran. */
const komposisiTone = (label: string) => {
  const map: Record<string, string> = {
    Hadir: 'bg-emerald-900',
    'Izin / Sakit': 'bg-amber-500',
    Alfa: 'bg-red-500',
  }
  return map[label] ?? 'bg-emerald-900'
}

const ringkasanSekolah = [
  { label: 'Rata-rata Kehadiran (Minggu Ini)', value: '89,4%', accent: true },
  { label: 'Total Izin / Sakit (Bulan Ini)', value: '18 Siswa', accent: false },
  { label: 'Alfa (Bulan Ini)', value: '9 Siswa', accent: false },
  { label: 'Hari Efektif (Bulan Ini)', value: '20 Hari', accent: false },
]
</script>
