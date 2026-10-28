<script setup lang="ts">
/*
 * Halaman lama "Lokasi Sekolah" sudah dipindah ke tab Lokasi di /pengaturan.
 * Redirect ini menjaga tautan/bookmark lama tetap bekerja.
 */
definePageMeta({
  layout: 'default',
  title: 'Lokasi Sekolah',
})

await navigateTo('/pengaturan', { replace: true })
</script>

<template>
  <div class="p-6 text-sm text-gray-500">Mengalihkan ke Pengaturan…</div>
</template>
