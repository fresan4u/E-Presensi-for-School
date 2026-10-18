<script setup lang="ts">
useHead({ title: 'Absensi Berhasil · Absensi Digital' })

const router = useRouter()
const { student } = useStudentAuth()

/** Hasil scan terakhir dari halaman scan. */
const lastScan = useState<ScanResult | null>('last-scan', () => null)

const data = computed(() => lastScan.value?.data ?? null)
/** false = absensi sesi ini sudah pernah tercatat sebelumnya. */
const sudahTercatat = computed(() => (lastScan.value ? !lastScan.value.meta.created : false))

const baris = computed(() => {
  const item = data.value
  if (!item) return []

  return [
    { label: 'Nama', value: student.value?.full_name ?? item.student?.full_name ?? '—' },
    { label: 'Tanggal', value: tanggalPanjang(item.attendance_date) },
    { label: 'Jam', value: waktu(item.checked_at) },
    { label: 'Sesi', value: item.session.label },
    { label: 'Metode Scan', value: item.method.label },
    { label: 'Status', value: item.status.label },
  ]
})

onMounted(() => {
  // Dibuka langsung tanpa data scan (mis. reload) -> kembali ke beranda.
  if (!data.value) router.replace('/siswa')
})
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-slate-50">
    <!-- ── Ikon + judul ── -->
    <header class="px-6 pt-[calc(env(safe-area-inset-top)+3rem)] pb-8 text-center">
      <div class="mx-auto flex size-24 items-center justify-center rounded-full bg-brand-50 text-brand-600">
        <AppIcon name="check" :size="48" :stroke-width="2.4" />
      </div>
      <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-slate-900">
        {{ sudahTercatat ? 'Absensi Sudah Tercatat' : 'Absensi Berhasil' }}
      </h1>
      <p class="mt-2 text-sm text-slate-500">
        {{
          sudahTercatat
            ? 'Kehadiran Anda pada sesi ini memang sudah tercatat sebelumnya.'
            : 'Kehadiran Anda hari ini sudah tercatat. Selamat belajar!'
        }}
      </p>
    </header>

    <!-- ── Detail kehadiran ── -->
    <main class="flex-1 px-5">
      <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="text-sm font-bold tracking-wide text-slate-900 uppercase">
          Detail Kehadiran
        </h2>
        <dl class="mt-4 space-y-4">
          <div v-for="row in baris" :key="row.label" class="flex items-center justify-between gap-4">
            <dt class="text-sm text-slate-500">{{ row.label }}</dt>
            <dd class="text-right text-sm font-bold text-slate-900">{{ row.value }}</dd>
          </div>
        </dl>
      </section>
    </main>

    <!-- ── Tombol kembali ke beranda ── -->
    <footer class="px-5 pt-6 pb-[calc(env(safe-area-inset-bottom)+1.5rem)]">
      <button type="button" class="btn-primary" @click="router.push('/siswa')">
        Kembali ke Beranda
      </button>
    </footer>
  </div>
</template>
