<template>
  <modal
    :model-value="modelValue"
    title="Edit Data Absensi"
    description="Perbarui status atau catatan kehadiran siswa."
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="flex flex-col gap-5">
      <!-- Info statis (identitas tidak bisa diubah di sini) -->
      <div class="rounded-md bg-slate-50 p-4">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
          <div class="flex items-center justify-between gap-3 sm:block">
            <dt class="text-gray-500 sm:inline">Nama&nbsp;&nbsp;</dt>
            <dd class="font-medium text-slate-800">{{ row?.name }}</dd>
          </div>
          <div class="flex items-center justify-between gap-3 sm:block">
            <dt class="text-gray-500 sm:inline">Kelas&nbsp;&nbsp;</dt>
            <dd class="font-medium text-slate-800">{{ row?.kelas }}</dd>
          </div>
          <div class="flex items-center justify-between gap-3 sm:block">
            <dt class="text-gray-500 sm:inline">Tanggal&nbsp;&nbsp;</dt>
            <dd class="font-medium text-slate-800">{{ row?.tanggal }}</dd>
          </div>
          <div class="flex items-center justify-between gap-3 sm:block">
            <dt class="text-gray-500 sm:inline">Jam&nbsp;&nbsp;</dt>
            <dd class="font-medium text-slate-800">{{ row?.jamMasuk }} – {{ row?.jamKeluar }}</dd>
          </div>
        </dl>
      </div>

      <!-- Form status (pill button) -->
      <div class="flex flex-col gap-2">
        <span class="text-sm font-medium text-slate-800">Status Kehadiran</span>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in ATTENDANCE_STATUSES"
            :key="status"
            type="button"
            class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors focus:outline-none"
            :class="
              form.status === status
                ? 'bg-emerald-900 text-white'
                : 'border border-gray-300 bg-white text-slate-700 hover:bg-emerald-50'
            "
            @click="form.status = status"
          >
            {{ STATUS_META[status].label }}
          </button>
        </div>
      </div>

      <!-- Form catatan -->
      <div class="flex flex-col gap-2">
        <label for="catatan" class="text-sm font-medium text-slate-800">Catatan</label>
        <textarea
          id="catatan"
          v-model="form.catatan"
          rows="3"
          class="w-full rounded-md border bg-white px-3 py-2.5 text-sm text-slate-800 placeholder:text-gray-400 transition-colors focus:border-emerald-900 focus:outline-none"
          placeholder="Alasan atau catatan tambahan (opsional)"
        />
      </div>
    </div>

    <!-- Footer khusus: Batal (abu-abu) + Simpan Perubahan (hijau) -->
    <template #footer>
      <button
        type="button"
        class="rounded-md bg-gray-100 px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-gray-200 focus:outline-none"
        @click="emit('update:modelValue', false)"
      >
        Batal
      </button>
      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-900 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-800 focus:outline-none"
        @click="save"
      >
        Simpan Perubahan
      </button>
    </template>
  </modal>
</template>

<script setup lang="ts">
const props = defineProps<{
  modelValue: boolean
  /** Baris absensi yang sedang diedit (null saat ditutup). */
  row: AttendanceRecord | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: [row: AttendanceRecord]
}>()

const form = reactive({
  status: 'hadir' as AttendanceStatus,
  catatan: '',
})

// Setiap kali target berubah, reset nilai form mengikuti baris.
watch(
  () => props.row,
  (row) => {
    if (!row) return
    form.status = row.status
    form.catatan = row.catatan ?? ''
  },
  { immediate: true },
)

const save = () => {
  if (!props.row) return
  emit('saved', {
    ...props.row,
    status: form.status,
    catatan: form.catatan.trim() || undefined,
  })
}
</script>