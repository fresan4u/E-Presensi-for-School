<template>
  <section class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
    <!-- Header opsional -->
    <header v-if="title || $slots.actions" class="mb-4 flex items-start justify-between gap-4">
      <div>
        <h3 v-if="title" class="text-base font-semibold tracking-tight text-slate-800">
          {{ title }}
        </h3>
        <p v-if="subtitle" class="mt-1 text-sm text-gray-500">{{ subtitle }}</p>
      </div>
      <slot name="actions" />
    </header>

    <slot />
  </section>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
  subtitle?: string
}>()
</script>
