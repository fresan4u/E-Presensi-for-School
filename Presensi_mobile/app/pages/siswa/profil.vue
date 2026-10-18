<script setup lang="ts">
useHead({ title: 'Profil · Absensi Digital' })

const router = useRouter()
const { student, fetchMe, logout } = useStudentAuth()

const isLoading = ref(false)

const avatarInisial = computed(() => inisial(student.value?.full_name ?? ''))

/** Informasi akun siswa yang ditampilkan (read-only). */
const infoAkun = computed(() => [
  { icon: 'user' as const, label: 'Nama Lengkap', value: student.value?.full_name ?? '—' },
  { icon: 'at-sign' as const, label: 'NIS', value: student.value?.nis ?? '—' },
  { icon: 'calendar' as const, label: 'Kelas', value: student.value?.classroom?.name ?? '—' },
])

onMounted(() => {
  fetchMe().catch(() => {})
})

async function onKeluar() {
  if (isLoading.value) return
  isLoading.value = true
  await logout()
  router.replace('/siswa/login')
}
</script>

<template>
  <div class="min-h-dvh bg-slate-50 pb-nav">
    <!-- ── Header hijau: avatar + identitas ── -->
    <section
      class="relative overflow-hidden rounded-b-3xl bg-brand-700 shadow-[0_12px_28px_-16px_rgba(2,6,23,0.45)]"
    >
      <header class="px-6 pt-[calc(env(safe-area-inset-top)+2.5rem)] pb-8 text-white">
        <h1 class="text-sm font-semibold tracking-wide text-white/80 uppercase">Profil</h1>

        <div class="mt-6 flex items-center gap-4">
          <span
            class="flex size-16 shrink-0 items-center justify-center rounded-full bg-white/15 text-xl font-bold ring-1 ring-white/25 ring-inset"
          >
            {{ avatarInisial }}
          </span>
          <div class="min-w-0">
            <p class="truncate text-xl font-extrabold tracking-tight">
              {{ student?.full_name ?? 'Siswa' }}
            </p>
            <p class="mt-0.5 text-sm text-white/70">
              Kelas {{ student?.classroom?.name ?? '—' }} · NIS {{ student?.nis ?? '—' }}
            </p>
          </div>
        </div>
      </header>
    </section>

    <main class="px-5">
      <!-- ── Kartu informasi akun ── -->
      <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="text-sm font-bold tracking-wide text-slate-900 uppercase">Informasi Akun</h2>

        <dl class="mt-4 divide-y divide-slate-100">
          <div
            v-for="row in infoAkun"
            :key="row.label"
            class="flex items-center gap-3 py-3.5 first:pt-0 last:pb-0"
          >
            <span
              class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"
            >
              <AppIcon :name="row.icon" :size="20" />
            </span>
            <dt class="text-sm text-slate-500">{{ row.label }}</dt>
            <dd class="ml-auto min-w-0 truncate text-right text-sm font-bold text-slate-900">
              {{ row.value }}
            </dd>
          </div>
        </dl>
      </section>

      <!-- ── Keamanan: ganti PIN ── -->
      <NuxtLink
        to="/siswa/ganti-pin"
        class="mt-4 flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm transition active:scale-[0.99]"
      >
        <span
          class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"
        >
          <AppIcon name="lock" :size="20" />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-sm font-bold text-slate-900">Ganti PIN</span>
          <span class="mt-0.5 block text-xs text-slate-400">
            {{ student?.must_change_pin ? 'PIN bawaan wajib diganti' : 'Ubah PIN rahasia akun Anda' }}
          </span>
        </span>
        <AppIcon name="chevron-right" :size="20" class="text-slate-300" />
      </NuxtLink>

      <!-- ── Tombol keluar ── -->
      <button
        type="button"
        class="btn-outline mt-4 border-danger-100 text-danger-600"
        :disabled="isLoading"
        @click="onKeluar"
      >
        <AppIcon name="logout" :size="20" />
        <span>{{ isLoading ? 'Keluar…' : 'Keluar' }}</span>
      </button>
    </main>

    <SiswaBottomnav active="profil" />
  </div>
</template>
