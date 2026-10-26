<template>
  <aside class="flex h-full w-64 shrink-0 flex-col bg-emerald-900">
    <!-- Bagian Atas: Logo / Nama Sistem -->
    <div class="flex items-center gap-3 px-6 py-6">
      <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10">
        <ShieldCheck class="h-5 w-5 text-white" />
      </div>
      <div class="flex flex-col">
        <span class="text-base font-bold leading-tight text-white">Presensi</span>
        <span class="text-xs leading-tight text-white/60">{{ subtitle }}</span>
      </div>
    </div>

    <!-- Daftar Navigasi -->
    <nav class="flex flex-1 flex-col gap-1 px-3 py-4">
      <NuxtLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-md px-4 py-3 text-sm transition-colors duration-150"
        :class="
          isActive(item)
            ? 'bg-emerald-800 font-medium text-white'
            : 'text-white/70 hover:bg-emerald-800/50 hover:text-white'
        "
        :aria-current="isActive(item) ? 'page' : undefined"
      >
        <component :is="item.icon" class="h-5 w-5 shrink-0" />
        <span>{{ item.label }}</span>
      </NuxtLink>
    </nav>

    <!-- Footer: Pindah panel + Keluar -->
    <div class="flex flex-col gap-1 border-t border-white/10 p-3">
      <!-- Pintasan ke aplikasi petugas (aplikasi terpisah, port 3001) -->
      <NuxtLink
        :to="resolvedGateway.to"
        class="flex items-center gap-3 rounded-md px-4 py-3 text-sm text-white/70 transition-colors duration-150 hover:bg-emerald-800/50 hover:text-white"
      >
        <ArrowLeftRight class="h-5 w-5 shrink-0" />
        <span>{{ resolvedGateway.label }}</span>
      </NuxtLink>

      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-md px-4 py-3 text-sm text-white/70 transition-colors duration-150 hover:bg-emerald-800/50 hover:text-white"
        @click="handleLogout"
      >
        <LogOut class="h-5 w-5 shrink-0" />
        <span>Keluar</span>
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ArrowLeftRight, Database, LayoutDashboard, LogOut, MapPin, ScanFace, ShieldCheck, UserCog } from '@lucide/vue'
import type { Component } from 'vue'

export interface SidebarItem {
  label: string
  to: string
  icon: Component
  /**
   * Cocokkan path secara persis (bukan prefix). Dipakai untuk item induk
   * seperti `/petugas` agar tidak ikut aktif di `/petugas/data-absensi`.
   */
  exact?: boolean
}

const props = withDefaults(
  defineProps<{
    /** Item menu navigasi. Default: menu dashboard admin. */
    items?: SidebarItem[]
    /** Teks kecil di bawah nama sistem. */
    subtitle?: string
    /** Link pintasan di footer (menuju aplikasi petugas). */
    gateway?: { label: string; to: string }
  }>(),
  {
    items: () => [
      { label: 'Dashboard', to: '/', icon: LayoutDashboard },
      { label: 'Data Siswa', to: '/siswa', icon: Database },
      { label: 'Registrasi Wajah', to: '/registrasi-wajah', icon: ScanFace },
      { label: 'Lokasi Sekolah', to: '/lokasi-sekolah', icon: MapPin },
      { label: 'Kelola Petugas', to: '/kelola-petugas', icon: UserCog },
    ],
    subtitle: 'Dashboard Admin',
  },
)

const route = useRoute()
const { logout } = useAuth()
const config = useRuntimeConfig()

/*
 * Panel Petugas kini aplikasi terpisah (Presensi_petugas, port 3001), jadi
 * pintasannya mengarah ke URL eksternal dari runtimeConfig. Bisa ditimpa
 * lewat prop `gateway`.
 */
const resolvedGateway = computed(
  () =>
    props.gateway ?? {
      label: 'Panel Petugas',
      to: (config.public.petugasUrl as string | undefined) || '/petugas',
    },
)

const isActive = (item: SidebarItem) => {
  if (item.exact) return route.path === item.to
  return item.to === '/' ? route.path === '/' : route.path.startsWith(item.to)
}

const handleLogout = async () => {
  await logout()
  await navigateTo('/login', { replace: true })
}
</script>