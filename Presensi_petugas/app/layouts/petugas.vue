<template>
  <!--
    Layout petugas responsif:
    - Layar lebar (>= md): sidebar kiri + topbar, mirip panel admin.
    - Layar kecil (< md): header ringkas + bottom-nav khusus mobile.
  -->
  <div class="flex min-h-dvh w-full bg-slate-50 md:h-dvh md:overflow-hidden">
    <!-- Sidebar: hanya tampil di layar lebar -->
    <layout-sidebar
      class="max-md:hidden"
      :items="navItems"
      subtitle="Panel Petugas"
    />

    <div class="flex min-w-0 flex-1 flex-col md:overflow-hidden">
      <!-- Topbar: hanya tampil di layar lebar -->
      <layout-topbar
        class="max-md:hidden"
        :title="currentPageTitle"
        :subtitle="currentPageSubtitle"
      />

      <!-- Header ringkas: hanya tampil di layar kecil -->
      <header
        class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-gray-200 bg-white px-4 md:hidden"
      >
        <div class="flex min-w-0 items-center gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-900">
            <ShieldCheck class="h-5 w-5 text-white" />
          </div>
          <div class="flex min-w-0 flex-col">
            <span class="truncate text-sm font-bold leading-tight text-slate-800">
              {{ currentPageTitle }}
            </span>
            <span class="truncate text-xs leading-tight text-gray-500">Panel Petugas</span>
          </div>
        </div>

        <button
          type="button"
          class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100"
          @click="handleLogout"
        >
          <LogOut class="h-4 w-4" />
          Keluar
        </button>
      </header>

      <!-- Konten utama -->
      <main class="flex-1 p-4 pb-28 md:overflow-y-auto md:p-6 md:pb-6">
        <slot />
      </main>
    </div>

    <!-- Bottom navigation: hanya tampil di layar kecil -->
    <nav
      class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden"
    >
      <div class="mx-auto flex max-w-md items-stretch">
        <NuxtLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex flex-1 flex-col items-center gap-1 px-2 py-3 text-xs font-medium transition-colors"
          :class="isActive(item) ? 'text-emerald-900' : 'text-gray-400 hover:text-slate-700'"
        >
          <component :is="item.icon" class="h-5 w-5" />
          {{ item.label }}
        </NuxtLink>
      </div>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { CalendarCheck, ClipboardList, LayoutDashboard, LogOut, Monitor, ShieldCheck } from '@lucide/vue'
import type { Component } from 'vue'

interface NavItem {
  label: string
  to: string
  icon: Component
  /** Cocokkan path persis (dipakai untuk Beranda `/petugas`). */
  exact?: boolean
}

const route = useRoute()
const { logout } = useAuth()

const navItems: NavItem[] = [
  { label: 'Beranda', to: '/petugas', icon: LayoutDashboard, exact: true },
  { label: 'Mesin Absensi', to: '/petugas/mesin-absen', icon: Monitor },
  { label: 'Rekap Absensi', to: '/petugas/rekap-harian', icon: ClipboardList },
  { label: 'Data Absensi', to: '/petugas/data-absensi', icon: CalendarCheck },
]

const pageTitles: Record<string, string> = {
  '/petugas': 'Dashboard',
  '/petugas/mesin-absen': 'Mesin Absensi',
  '/petugas/rekap-harian': 'Rekap Absensi',
  '/petugas/data-absensi': 'Data Absensi',
  '/scan': 'Scan Absensi',
}

const pageSubtitles: Record<string, string> = {
  '/petugas': 'Ringkasan kehadiran siswa hari ini',
  '/petugas/mesin-absen': 'Pindai wajah siswa untuk mencatat kehadiran',
  '/petugas/rekap-harian': 'Rekap kehadiran satu kelas pada tanggal tertentu',
  '/petugas/data-absensi': 'Rekap seluruh data kehadiran',
}

const currentPageTitle = computed(() => pageTitles[route.path] ?? 'Panel Petugas')
const currentPageSubtitle = computed(() => pageSubtitles[route.path] ?? '')

const isActive = (item: NavItem) => (item.exact ? route.path === item.to : route.path.startsWith(item.to))

const handleLogout = async () => {
  await logout()
  await navigateTo('/login', { replace: true })
}
</script>
