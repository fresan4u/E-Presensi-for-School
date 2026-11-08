
<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-medium transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#006FBE] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    :class="variant === 'solid'
      ? 'bg-[#006FBE] text-white hover:bg-[#004A80]'
      : 'border border-[#006FBE] bg-white text-[#006FBE] hover:bg-[#EAF4FC]'"
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
