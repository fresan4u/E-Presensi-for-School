<template>
  <div class="overflow-x-auto rounded-lg border border-gray-100 bg-white shadow-sm">
    <table class="w-full border-collapse text-sm">
      <!-- Header tabel -->
      <thead class="bg-slate-50/80">
        <tr>
          <th
            v-for="column in columns"
            :key="column.key"
            scope="col"
            class="whitespace-nowrap px-4 py-3 text-left font-semibold text-slate-800"
            :class="alignClass(column.align)"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>

      <!-- Body -->
      <tbody>
        <tr
          v-for="(row, rowIndex) in rows"
          :key="rowIndex"
          class="border-b border-gray-100 transition-colors last:border-0 hover:bg-slate-50/60"
        >
          <td
            v-for="column in columns"
            :key="column.key"
            class="px-4 py-3 text-slate-800"
            :class="alignClass(column.align)"
          >
            <!-- Override per kolom: <template #[`cell-${key}`]> -->
            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
              {{ formatValue(row[column.key]) }}
            </slot>
          </td>
        </tr>

        <!-- Empty state -->
        <tr v-if="!rows.length">
          <td :colspan="columns.length" class="px-4 py-10 text-center text-gray-500">
            {{ emptyText }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
export interface TableColumn {
  key: string
  label: string
  align?: 'left' | 'center' | 'right'
}

withDefaults(
  defineProps<{
    columns: TableColumn[]
    rows?: Record<string, unknown>[]
    emptyText?: string
  }>(),
  {
    rows: () => [],
    emptyText: 'Tidak ada data',
  },
)

const alignClass = (align?: TableColumn['align']) => {
  if (align === 'right') return 'text-right'
  if (align === 'center') return 'text-center'
  return 'text-left'
}

const formatValue = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '-'
  if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak'
  return String(value)
}
</script>
