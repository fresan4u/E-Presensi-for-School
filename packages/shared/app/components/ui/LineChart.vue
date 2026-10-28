<template>
  <div class="flex w-full select-none gap-3 sm:gap-4">
    <!-- Sumbu Y -->
    <div class="relative w-9 shrink-0 sm:w-10" :class="heightClass" aria-hidden="true">
      <span
        v-for="tick in ticks"
        :key="tick.value"
        class="absolute right-0 -translate-y-1/2 text-[10px] font-medium tabular-nums text-slate-400"
        :style="{ top: `${tick.top}%` }"
      >
        {{ tick.label }}
      </span>
    </div>

    <div class="min-w-0 flex-1">
      <!-- Area plot -->
      <div
        class="relative"
        :class="heightClass"
        @mouseleave="hoverIndex = null"
      >
        <svg
          class="absolute inset-0 h-full w-full overflow-visible"
          viewBox="0 0 100 100"
          preserveAspectRatio="none"
          role="img"
          :aria-label="ariaLabel"
        >
          <defs>
            <linearGradient
              v-for="(line, index) in plotted"
              :id="gradientId(index)"
              :key="`gradient-${index}`"
              x1="0"
              y1="0"
              x2="0"
              y2="1"
            >
              <stop offset="0%" :stop-color="line.color" stop-opacity="0.26" />
              <stop offset="100%" :stop-color="line.color" stop-opacity="0.01" />
            </linearGradient>
          </defs>

          <!-- Grid horizontal -->
          <line
            v-for="tick in ticks"
            :key="`grid-${tick.value}`"
            x1="0"
            :y1="tick.top"
            x2="100"
            :y2="tick.top"
            :stroke="tick.value === 0 ? '#e2e8f0' : '#eef2f6'"
            stroke-width="1"
            vector-effect="non-scaling-stroke"
          />

          <!-- Satu kurva per seri -->
          <template v-for="(line, index) in plotted" :key="`series-${index}`">
            <path
              v-if="line.area"
              :d="line.areaPath"
              :fill="`url(#${gradientId(index)})`"
              stroke="none"
            />
            <path
              :d="line.path"
              fill="none"
              :stroke="line.color"
              stroke-width="2.25"
              stroke-linecap="round"
              stroke-linejoin="round"
              vector-effect="non-scaling-stroke"
            />
          </template>
        </svg>

        <!-- Garis penunjuk saat hover -->
        <span
          v-if="hovered"
          class="pointer-events-none absolute inset-y-0 w-px bg-slate-300/80 transition-opacity"
          :style="{ left: `${hovered.x}%` }"
        />

        <!-- Titik data pakai HTML supaya tetap bulat saat SVG diregangkan -->
        <template v-for="(line, index) in plotted" :key="`dots-${index}`">
          <span
            v-for="(dot, dotIndex) in line.dots"
            :key="dotIndex"
            class="pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white transition-all duration-150"
            :class="hoverIndex === dotIndex ? 'h-3 w-3 shadow-sm' : 'h-2 w-2'"
            :style="{ left: `${dot.x}%`, top: `${dot.y}%`, backgroundColor: line.color }"
          />
        </template>

        <!-- Area tangkap hover per slot -->
        <div class="absolute inset-0 flex">
          <div
            v-for="(label, index) in labels"
            :key="`hit-${index}`"
            class="h-full flex-1"
            :aria-label="`${label}: ${series.map((item) => `${item.label} ${item.values[index] ?? 0}${valueSuffix}`).join(', ')}`"
            @mouseenter="hoverIndex = index"
            @touchstart.prevent="hoverIndex = index"
          />
        </div>

        <!-- Tooltip -->
        <div
          v-if="hovered"
          class="pointer-events-none absolute top-1 z-20 min-w-36 rounded-lg border border-slate-100 bg-white/95 px-3 py-2 shadow-lg shadow-slate-900/5 backdrop-blur transition-opacity"
          :class="tooltipAlignClass"
          :style="{ left: `${hovered.x}%` }"
        >
          <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            {{ labels[hovered.index] }}
          </p>
          <ul class="flex flex-col gap-1">
            <li
              v-for="item in hovered.values"
              :key="item.label"
              class="flex items-center gap-2 text-xs"
            >
              <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: item.color }" />
              <span class="text-slate-500">{{ item.label }}</span>
              <span class="ml-auto pl-3 font-semibold tabular-nums text-slate-800">
                {{ item.value }}{{ valueSuffix }}
              </span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Label sumbu X -->
      <div class="mt-2.5 flex border-t border-slate-100 pt-2">
        <span
          v-for="(label, index) in labels"
          :key="`x-${index}`"
          class="flex-1 text-center text-[11px] text-slate-400"
          :class="hoverIndex === index ? 'font-semibold text-slate-600' : ''"
        >
          {{ label }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useId } from 'vue'

export interface LineChartSeries {
  label: string
  /** Warna garis (nilai CSS, mis. `#064e3b`). */
  color: string
  values: number[]
  /** Isi area di bawah garis dengan gradasi warna seri. */
  area?: boolean
}

const props = withDefaults(
  defineProps<{
    labels: string[]
    series: LineChartSeries[]
    /** Skala maksimum; bila kosong dihitung otomatis dari data. */
    maxValue?: number
    /** Suffix label sumbu Y dan tooltip (mis. `%`). */
    valueSuffix?: string
    /** Kelas tinggi area plot (mis. `h-56`). */
    heightClass?: string
  }>(),
  {
    maxValue: undefined,
    valueSuffix: '',
    heightClass: 'h-56',
  },
)

const uid = useId()
const gradientId = (index: number) => `${uid}-line-gradient-${index}`

const hoverIndex = ref<number | null>(null)

const ariaLabel = computed(
  () => `Grafik garis ${props.labels.join(', ')}`,
)

const max = computed(() => {
  if (props.maxValue && props.maxValue > 0) return props.maxValue

  const raw = Math.max(1, ...props.series.flatMap((item) => item.values))
  if (raw <= 4) return raw

  // Bulatkan ke angka "cantik" (1, 2, 5 × 10^n) agar sumbu Y mudah dibaca.
  const magnitude = 10 ** Math.floor(Math.log10(raw))
  const normalized = raw / magnitude
  const nice = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10
  return nice * magnitude
})

/** Titik diletakkan di tengah tiap slot supaya sejajar dengan label X. */
const xPercent = (index: number) => {
  const count = Math.max(1, props.labels.length)
  return ((index + 0.5) / count) * 100
}

const yPercent = (value: number) => {
  const ratio = value / max.value
  return Math.max(0, Math.min(100, 100 - ratio * 100))
}

const clampY = (value: number) => Math.max(0, Math.min(100, value))
const round = (value: number) => Math.round(value * 1000) / 1000

/**
 * Kurva halus Catmull-Rom → cubic Bézier. Nilai kontrol Y dijepit ke area
 * plot supaya kurva tidak melewati garis 0/100 saat ada lonjakan data.
 */
const smoothPath = (dots: Array<{ x: number; y: number }>) => {
  if (dots.length === 0) return ''
  if (dots.length === 1) return `M ${round(dots[0]!.x)} ${round(dots[0]!.y)}`

  let path = `M ${round(dots[0]!.x)} ${round(dots[0]!.y)}`

  for (let index = 0; index < dots.length - 1; index++) {
    const previous = dots[index - 1] ?? dots[index]!
    const current = dots[index]!
    const next = dots[index + 1]!
    const after = dots[index + 2] ?? next

    const cp1x = current.x + (next.x - previous.x) / 6
    const cp1y = clampY(current.y + (next.y - previous.y) / 6)
    const cp2x = next.x - (after.x - current.x) / 6
    const cp2y = clampY(next.y - (after.y - current.y) / 6)

    path += ` C ${round(cp1x)} ${round(cp1y)}, ${round(cp2x)} ${round(cp2y)}, ${round(next.x)} ${round(next.y)}`
  }

  return path
}

const plotted = computed(() =>
  props.series.map((line) => {
    const dots = line.values.map((value, index) => ({
      x: xPercent(index),
      y: yPercent(value),
      value,
    }))

    const first = dots[0]
    const last = dots[dots.length - 1]
    const path = smoothPath(dots)
    const areaPath = first && last
      ? `${path} L ${round(last.x)} 100 L ${round(first.x)} 100 Z`
      : ''

    return { ...line, dots, path, areaPath }
  }),
)

const ticks = computed(() => {
  const values = new Set<number>()

  if (max.value <= 8) {
    // Skala kecil (jumlah siswa): tampilkan semua angka bulat agar tidak ada
    // label pecahan/duplikat.
    for (let value = 0; value <= max.value; value++) values.add(value)
  } else {
    const steps = 4
    for (let index = 0; index <= steps; index++) {
      values.add(Math.round((max.value * index) / steps))
    }
  }

  return [...values]
    .sort((a, b) => a - b)
    .map((value) => ({
      value,
      top: yPercent(value),
      label: `${value}${props.valueSuffix}`,
    }))
})

const hovered = computed(() => {
  if (hoverIndex.value === null) return null

  const index = hoverIndex.value
  const x = xPercent(index)

  return {
    index,
    x,
    values: props.series.map((line) => ({
      label: line.label,
      color: line.color,
      value: line.values[index] ?? 0,
    })),
  }
})

/** Jaga tooltip tetap di dalam area plot, termasuk saat titik di ujung. */
const tooltipAlignClass = computed(() => {
  if (!hovered.value) return ''
  if (hovered.value.x < 16) return ''
  if (hovered.value.x > 84) return '-translate-x-full'
  return '-translate-x-1/2'
})
</script>
