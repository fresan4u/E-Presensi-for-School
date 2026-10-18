<template>
  <nav
    v-if="totalPages > 1"
    class="flex items-center justify-end gap-2"
    aria-label="Navigasi halaman"
  >
    <!-- Sebelumnya -->
    <button
      type="button"
      class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-white text-slate-800 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
      :disabled="currentPage <= 1"
      aria-label="Halaman sebelumnya"
      @click="goTo(currentPage - 1)"
    >
      <ChevronLeft class="h-4 w-4" />
    </button>

    <!-- Nomor halaman -->
    <button
      v-for="page in visiblePages"
      :key="page"
      type="button"
      class="h-9 min-w-9 rounded-md px-2 text-sm transition-colors"
      :class="page === currentPage
        ? 'bg-emerald-900 font-medium text-white'
        : 'border border-gray-200 bg-white text-slate-800 hover:bg-slate-50'"
      :aria-current="page === currentPage ? 'page' : undefined"
      @click="goTo(page)"
    >
      {{ page }}
    </button>

    <!-- Berikutnya -->
    <button
      type="button"
      class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-white text-slate-800 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
      :disabled="currentPage >= totalPages"
      aria-label="Halaman berikutnya"
      @click="goTo(currentPage + 1)"
    >
      <ChevronRight class="h-4 w-4" />
    </button>
  </nav>
</template>

<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue'

const props = withDefaults(
  defineProps<{
    currentPage: number
    totalPages: number
    /** Maksimal nomor halaman yang ditampilkan sekaligus. */
    maxButtons?: number
  }>(),
  {
    maxButtons: 5,
  },
)

const emit = defineEmits<{ 'update:currentPage': [page: number] }>()

/** Nomor halaman yang tampil, tetap terpusat di halaman aktif. */
const visiblePages = computed(() => {
  const total = Math.max(1, props.totalPages)
  const current = Math.min(Math.max(1, props.currentPage), total)
  const max = Math.max(3, props.maxButtons)

  let start = Math.max(1, current - Math.floor(max / 2))
  const end = Math.min(total, start + max - 1)
  start = Math.max(1, end - max + 1)

  const pages: number[] = []
  for (let page = start; page <= end; page++) pages.push(page)
  return pages
})

const goTo = (page: number) => {
  const next = Math.min(Math.max(1, page), props.totalPages)
  if (next !== props.currentPage) emit('update:currentPage', next)
}
</script>
