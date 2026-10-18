<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
    >
      <!-- Latar belakang gelap + blur -->
      <div
        class="absolute inset-0 bg-black/40 backdrop-blur-sm"
        @click="close"
      />

      <!-- Kontainer modal -->
      <div class="relative z-10 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
        <!-- Header -->
        <div class="mb-4 flex items-start justify-between gap-4">
          <div>
            <h2 v-if="title" class="text-lg font-semibold tracking-tight text-slate-800">
              {{ title }}
            </h2>
            <p v-if="description" class="mt-1 text-sm text-gray-500">{{ description }}</p>
          </div>
          <button
            type="button"
            class="rounded-md p-1 text-gray-400 transition-colors hover:bg-slate-50 hover:text-slate-800 focus:outline-none"
            aria-label="Tutup"
            @click="close"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <!-- Konten form -->
        <slot />

        <!-- Footer: Batal (outline) + Simpan (solid hijau tua) -->
        <footer class="mt-6 flex justify-end gap-4">
          <slot name="footer">
            <base-button variant="outline" @click="close">Batal</base-button>
            <base-button variant="solid" :loading="loading" @click="emit('confirm')">
              Simpan
            </base-button>
          </slot>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { X } from '@lucide/vue'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    description?: string
    loading?: boolean
  }>(),
  {
    title: '',
    description: '',
    loading: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
}>()

const close = () => emit('update:modelValue', false)

// Tutup dengan tombol Escape.
const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && props.modelValue) close()
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>
