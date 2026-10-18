<template>
  <div class="flex flex-col gap-2">
    <label v-if="label" :for="inputId" class="text-sm font-medium text-slate-800">
      {{ label }}
    </label>
    <div class="relative">
      <slot name="prefix" />
      <input
        :id="inputId"
        :value="modelValue"
        :type="type"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :required="required"
        class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 placeholder:text-gray-400 transition-colors focus:border-emerald-900 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50"
        :class="[hasPrefix ? 'pl-9' : '', hasSuffix ? 'pr-10' : '', customClass]"
        @input="onInput"
      />
      <slot name="suffix" />
    </div>
    <p v-if="error" class="text-xs text-red-600" role="alert">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: string
    label?: string
    type?: string
    placeholder?: string
    autocomplete?: string
    required?: boolean
    error?: string
    customClass?: string
  }>(),
  {
    modelValue: '',
    label: '',
    type: 'text',
    placeholder: '',
    autocomplete: 'off',
    required: false,
    error: '',
    customClass: '',
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const inputId = useId()

/** Ada slot prefix/suffix yang di-render? (untuk memberi jarak teks). */
const slots = useSlots()
const hasPrefix = computed(() => Boolean(slots.prefix))
const hasSuffix = computed(() => Boolean(slots.suffix))

const onInput = (event: Event) => {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>
