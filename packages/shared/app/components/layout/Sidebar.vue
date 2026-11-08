<template>
  <aside class="flex h-full w-64 shrink-0 flex-col" :class="SIDEBAR_CLASS">
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
        :class="isActive(item) ? activeClass : INACTIVE_CLASS"
        :aria-current="isActive(item) ? 'page' : undefined"
      >
        <component :is="item.icon" class="h-5 w-5 shrink-0" />
        <span>{{ item.label }}</span>
      </NuxtLink>
    </nav>

    <!-- Footer: Keluar -->
    <div class="flex flex-col gap-1 border-t border-white/10 p-3">
      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-md px-4 py-3 text-sm text-white/70 transition-colors duration-150 hover:bg-[#244967] hover:text-white"
        @click="handleLogout"
      >
        <LogOut class="h-5 w-5 shrink-0" />
        <span>Keluar</span>
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import {
  Database,
  LayoutDashboard,
  LogOut,
  Settings,
  ShieldCheck,
  UsersRound,
} from '@lucide/vue'

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
    /** Warna menu aktif: 'gold' (emas) atau 'blue' (biru). */
    variant?: 'blue' | 'gold'
  }>(),
  {
    items: () => [
      { label: 'Dashboard', to: '/', icon: LayoutDashboard },
      { label: 'Data Siswa', to: '/siswa', icon: Database },
      { label: 'Kelola Petugas', to: '/kelola-petugas', icon: UsersRound },
      { label: 'Pengaturan', to: '/pengaturan', icon: Settings },
    ],
    subtitle: 'Dashboard Admin',
    variant: 'gold',
  },
)

/* ------------------------------------------------------------------ */
/* Gaya                                                                */
/* ------------------------------------------------------------------ */

const SIDEBAR_CLASS = 'bg-[#17324D]'

const INACTIVE_CLASS = 'text-white/70 hover:bg-[#244967] hover:text-white'

const activeClass = computed(() =>
  props.variant === 'gold'
    ? 'bg-[#D4A017] font-medium text-[#17324D]'
    : 'bg-[#006FBE] font-medium text-white',
)

/* ------------------------------------------------------------------ */
/* Navigasi                                                            */
/* ------------------------------------------------------------------ */

const route = useRoute()
const { logout } = useAuth()

const isActive = (item: SidebarItem) => {
  if (item.exact) return route.path === item.to
  return item.to === '/' ? route.path === '/' : route.path.startsWith(item.to)
}

const handleLogout = async () => {
  await logout()
  await navigateTo('/login', { replace: true })
}
</script>