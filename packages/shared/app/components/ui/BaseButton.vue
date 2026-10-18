<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-900 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    :class="variant === 'solid'
      ? 'bg-emerald-900 text-white hover:bg-emerald-800'
      : 'border border-gray-300 bg-white text-slate-800 hover:bg-slate-50'"
  >
    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
    <slot />
  </button>
</template>

<script setup lang="ts">
import { Loader2 } from '@lucide/vue'

withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset'
    variant?: 'solid' | 'outline'
    disabled?: boolean
    loading?: boolean
  }>(),
  {
    type: 'button',
    variant: 'solid',
    disabled: false,
    loading: false,
  },
)
</script>
