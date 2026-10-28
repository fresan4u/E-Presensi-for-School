<script setup lang="ts">
useHead({ title: 'Beranda · Absensi Digital' })

const { student, fetchMe } = useStudentAuth()
const api = useApi()

const loading = ref(true)
const errorMessage = ref('')
/** Absensi sesi masuk hari ini (null = belum absen). */
const absenMasuk = ref<ApiAttendance | null>(null)
/** Absensi sesi pulang hari ini (null = belum absen). */
const absenPulang = ref<ApiAttendance | null>(null)
/** Absensi terbaru (maks. 5). */
const riwayatTerbaru = ref<ApiAttendance[]>([])
/** Batas jam absen mandiri dari server, termasuk jadwal per hari & libur. */
const jendela = ref<ApiAttendanceWindow | null>(null)
/** Jam sekarang (untuk menentukan sesi & tombol tutup). */
const sekarang = ref(new Date())

const avatarInisial = computed(() => inisial(student.value?.full_name ?? ''))
const tanggalHariIni = computed(() => tanggalPanjang(sekarang.value))

/** Menit sejak tengah malam dari string "HH:MM" (null bila tidak valid). */
function keMenit(jam: string | null | undefined): number | null {
  if (!jam) return null
  const [h, m] = jam.split(':').map(Number)
  if (!Number.isFinite(h) || !Number.isFinite(m)) return null
  return (h ?? 0) * 60 + (m || 0)
}

/** Sesi yang sedang berlaku menurut jam (fallback: masuk). */
const sesiSaatIni = computed<'masuk' | 'pulang'>(() => {
  if (!jendela.value) return 'masuk'
  const menitSekarang = sekarang.value.getHours() * 60 + sekarang.value.getMinutes()
  const pulangFrom = keMenit(jendela.value.pulang_from) ?? 15 * 60
  return menitSekarang >= pulangFrom ? 'pulang' : 'masuk'
})

/** Absen mandiri sedang dibuka? (hari sekolah, bukan libur, di dalam jendela). */
const absenDibuka = computed(() => {
  const w = jendela.value
  if (!w) return true
  if (!w.self_scan_enabled || w.is_holiday || !w.is_school_day) return false

  const menitSekarang = sekarang.value.getHours() * 60 + sekarang.value.getMinutes()
  const dalamJendela = (
    mulai: string | null,
    selesai: string | null,
    fallbackSelesai: string | null,
  ) => {
    const from = keMenit(mulai)
    const to = keMenit(selesai ?? fallbackSelesai)
    return from !== null && to !== null && menitSekarang >= from && menitSekarang <= to
  }

  return dalamJendela(w.masuk_start ?? '00:00', w.masuk_end, w.self_cutoff)
    || dalamJendela(w.pulang_from, w.pulang_end, '23:59')
})

/** Pesan singkat mengapa absen ditutup (dari server, fallback lokal). */
const alasanTutup = computed(() => {
  const w = jendela.value
  if (!w) return 'Di luar jam absen mandiri.'
  if (w.is_holiday) return `Hari ini libur${w.holiday_name ? `: ${w.holiday_name}` : ''}.`
  if (!w.self_scan_enabled) return 'Absensi mandiri sedang dinonaktifkan admin.'
  if (!w.is_school_day) return 'Tidak ada jadwal absensi untuk hari ini.'
  return `Absen masuk sampai ${w.masuk_end ?? w.self_cutoff}; absen pulang mulai ${w.pulang_from}.`
})

/** Status absen relevan untuk kartu (sesuai jam saat ini). */
const absenSaatIni = computed(() =>
  sesiSaatIni.value === 'pulang' ? absenPulang.value : absenMasuk.value,
)

/** Tanggal hari ini dalam format YYYY-MM-DD (zona lokal). */
function tanggalLokal(): string {
  const d = new Date()
  const bulan = String(d.getMonth() + 1).padStart(2, '0')
  const tgl = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${bulan}-${tgl}`
}

async function muat() {
  loading.value = true
  errorMessage.value = ''
  try {
    const profil = await fetchMe()
    if (!profil) return

    const tanggal = tanggalLokal()
    const [masuk, pulang, recent, meta] = await Promise.all([
      api.studentAttendances({ date: tanggal, session: 'masuk', per_page: 1 }),
      api.studentAttendances({ date: tanggal, session: 'pulang', per_page: 1 }),
      api.studentAttendances({ per_page: 5 }),
      api.studentMeta(),
    ])

    absenMasuk.value = masuk.data[0] ?? null
    absenPulang.value = pulang.data[0] ?? null
    riwayatTerbaru.value = recent.data
    jendela.value = meta.data.attendance_window
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  } finally {
    loading.value = false
  }
}

let jamTimer: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  muat()
  // Perbarui jam tiap 30 detik supaya tombol tutup/buka ikut menyesuaikan.
  jamTimer = setInterval(() => {
    sekarang.value = new Date()
  }, 30_000)
})

onBeforeUnmount(() => {
  if (jamTimer) clearInterval(jamTimer)
})
</script>

<template>
  <div class="min-h-dvh bg-white pb-nav">
    <!-- ══ SATU BLOK: area hijau + kartu status menyatu ══ -->
    <section
      class="relative overflow-hidden rounded-b-3xl bg-brand-700 shadow-[0_12px_28px_-16px_rgba(2,6,23,0.45)]"
    >
      <!-- ── Isi header hijau ── -->
      <header class="px-6 pt-[calc(env(safe-area-inset-top)+2.5rem)] text-white">
        <div class="flex items-start justify-between">
          <!-- Logo + nama aplikasi -->
          <div class="flex items-center gap-2.5">
            <span
              class="flex size-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 ring-inset"
            >
              <AppIcon name="scan-face" :size="19" />
            </span>
            <span class="text-sm font-semibold tracking-wide text-white/90 uppercase">
              Absensi Digital
            </span>
          </div>

          <!-- Inisial profil -->
          <NuxtLink
            to="/siswa/profil"
            class="flex size-10 items-center justify-center rounded-full bg-white/15 text-sm font-bold ring-1 ring-white/25 ring-inset transition active:scale-95"
            :aria-label="`Profil ${student?.full_name ?? 'siswa'}`"
          >
            {{ avatarInisial }}
          </NuxtLink>
        </div>

        <!-- Sapaan + tanggal -->
        <div class="mt-7">
          <h1 class="text-[26px] leading-tight font-extrabold tracking-tight">
            Halo, {{ student?.full_name ?? 'Siswa' }}!
          </h1>
          <p class="mt-1.5 text-sm text-white/70">{{ tanggalHariIni }}</p>
          <p class="mt-0.5 text-xs text-white/50">
            Kelas {{ student?.classroom?.name ?? '—' }} · NIS {{ student?.nis ?? '—' }}
          </p>
        </div>
      </header>

      <!-- ── Kartu status, menempel di dasar blok (pt-6 / pb-5) ── -->
      <div class="px-5 pt-6 pb-5">
        <section class="rounded-2xl bg-white p-5 shadow-lg shadow-slate-900/15">
          <template v-if="absenSaatIni">
            <div class="flex items-center gap-4">
              <span
                class="flex size-12 items-center justify-center rounded-2xl"
                :class="gayaStatus(absenSaatIni.status.code).ikonBox"
              >
                <AppIcon
                  :name="gayaStatus(absenSaatIni.status.code).ikon"
                  :size="26"
                  :stroke-width="2"
                />
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <h2 class="text-base font-bold text-slate-900">
                    {{ sesiSaatIni === 'pulang' ? 'Sudah Pulang' : 'Sudah Absen' }}
                  </h2>
                  <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                    :class="gayaStatus(absenSaatIni.status.code).badge"
                  >
                    {{ absenSaatIni.status.label }}
                  </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">
                  Jam {{ sesiSaatIni === 'pulang' ? 'kepulangan' : 'kedatangan' }}
                  <span class="font-semibold text-slate-700">{{ waktu(absenSaatIni.checked_at) }}</span>
                </p>
              </div>
            </div>

            <div
              class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3"
            >
              <span class="shrink-0 text-xs font-medium text-slate-500">Metode absen</span>
              <span class="flex min-w-0 items-center gap-1.5 text-xs font-bold text-slate-800">
                <AppIcon name="user-scan" :size="16" class="text-brand-600" />
                <span class="truncate">{{ absenSaatIni.method.label }}</span>
              </span>
            </div>
          </template>

          <template v-else>
            <div class="flex items-center gap-4">
              <span
                class="flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
              >
                <AppIcon name="clock" :size="26" :stroke-width="2" />
              </span>
              <div>
                <h2 class="text-base font-bold text-slate-900">
                  {{ loading ? 'Memuat…' : 'Belum Absen' }}
                </h2>
                <p class="mt-0.5 text-sm text-slate-500">
                  {{
                    absenDibuka
                      ? `Absen ${sesiSaatIni === 'pulang' ? 'pulang' : 'masuk'} dibuka.`
                      : 'Absen ditutup. Hubungi petugas bila perlu.'
                  }}
                </p>
              </div>
            </div>
          </template>
        </section>
      </div>
    </section>

    <!-- ── Konten di bawah blok ── -->
    <div class="px-5">
      <!-- ── Tombol scan utama ── -->
      <NuxtLink
        v-if="absenDibuka"
        to="/siswa/scan"
        class="mt-5 flex w-full items-center gap-4 rounded-2xl bg-brand-700 p-4 text-left text-white shadow-xl shadow-brand-700/25 transition active:scale-[0.98] active:bg-brand-800"
      >
        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white/15">
          <AppIcon name="scan-face" :size="26" />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-base font-bold">
            Scan Wajah {{ sesiSaatIni === 'pulang' ? 'Pulang' : 'Masuk' }}
          </span>
          <span class="mt-0.5 block text-xs text-white/70">
            Klik untuk melakukan absensi sekarang
          </span>
        </span>
        <AppIcon name="chevron-right" :size="22" class="text-white/70" />
      </NuxtLink>

      <!-- Absen ditutup: tombol disabled + info jam berikutnya -->
      <div
        v-else
        class="mt-5 flex w-full items-center gap-4 rounded-2xl bg-slate-100 p-4 text-left text-slate-400"
        aria-disabled="true"
      >
        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-slate-200/70">
          <AppIcon name="clock" :size="26" />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-base font-bold text-slate-500">Absen Ditutup</span>
          <span class="mt-0.5 block text-xs text-slate-400">
            <template v-if="jendela">
              {{ alasanTutup }}
            </template>
            <template v-else>Di luar jam absen mandiri.</template>
          </span>
        </span>
      </div>

      <p
        v-if="errorMessage"
        class="mt-4 rounded-xl bg-danger-50 px-4 py-3 text-xs font-medium text-danger-600"
        role="alert"
      >
        {{ errorMessage }}
        <button type="button" class="ml-1 underline" @click="muat">Coba lagi</button>
      </p>

      <!-- ── Daftar absensi terbaru ── -->
      <section class="mt-7">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="section-title">Absensi Terbaru</h2>
          <NuxtLink
            to="/siswa/riwayat"
            class="text-sm font-semibold text-brand-700 active:text-brand-800"
          >
            Lihat Semua
          </NuxtLink>
        </div>

        <ul
          v-if="riwayatTerbaru.length"
          class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100"
        >
          <li
            v-for="item in riwayatTerbaru"
            :key="item.id"
            class="flex items-center gap-3 px-4 py-3.5"
          >
            <span
              class="flex size-10 shrink-0 items-center justify-center rounded-xl"
              :class="gayaStatus(item.status.code).ikonBox"
            >
              <AppIcon
                :name="gayaStatus(item.status.code).ikon"
                :size="20"
                :stroke-width="2.4"
              />
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-800">
                {{ tanggalPendek(item.attendance_date) }}
              </p>
              <p class="text-xs text-slate-400">
                {{ waktu(item.checked_at) }} · {{ item.method.label }}
              </p>
            </div>
            <span
              class="rounded-full px-2.5 py-1 text-[11px] font-bold"
              :class="gayaStatus(item.status.code).badge"
            >
              {{ item.status.label }}
            </span>
          </li>
        </ul>

        <p
          v-else-if="!loading"
          class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400"
        >
          Belum ada riwayat absensi.
        </p>
      </section>
    </div>

    <SiswaBottomnav active="beranda" />
  </div>
</template>
