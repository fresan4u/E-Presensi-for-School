<script setup lang="ts">
useHead({ title: 'Ganti PIN · Absensi Digital' })

const router = useRouter()
const { student, changePin, logout } = useStudentAuth()

const form = reactive({ current_pin: '', pin: '', pin_confirmation: '' })
const showPin = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const wajib = computed(() => Boolean(student.value?.must_change_pin))

/** PIN hanya bisa diganti sekali: `can_change_pin` dari server. */
const bisaGanti = computed(() => student.value?.can_change_pin !== false)

const bisaSubmit = computed(
  () =>
    form.current_pin.length > 0 &&
    form.pin.length >= 4 &&
    form.pin === form.pin_confirmation,
)

async function onSubmit() {
  errorMessage.value = ''
  if (!bisaSubmit.value || isLoading.value) return

  isLoading.value = true
  try {
    await changePin({ ...form })
    router.replace('/siswa')
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
    isLoading.value = false
  }
}

async function onKeluar() {
  await logout()
  router.replace('/siswa/login')
}
</script>

<template>
  <div class="min-h-dvh bg-white">
    <!-- ── Area hijau ── -->
    <div class="bg-brand-700 px-6 pt-[calc(env(safe-area-inset-top)+2.5rem)] pb-28">
      <div class="flex items-center gap-2.5">
        <span
          class="flex size-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 ring-inset"
        >
          <AppIcon name="lock" :size="19" class="text-white" />
        </span>
        <span class="text-sm font-semibold tracking-wide text-white/90 uppercase">
          Absensi Digital
        </span>
      </div>

      <h1 class="mt-8 text-3xl font-extrabold tracking-tight text-white">
        {{ wajib ? 'Ganti PIN' : 'PIN Terkunci' }}
      </h1>
      <p class="mt-2 max-w-[18rem] text-sm leading-relaxed text-white/70">
        {{
          wajib
            ? 'Demi keamanan, ganti PIN bawaan Anda sebelum mulai menggunakan aplikasi.'
            : 'PIN hanya dapat diganti sekali dan sudah pernah diubah.'
        }}
      </p>
    </div>

    <!-- ── Kartu form ── -->
    <div class="-mt-20 px-5 pb-10">
      <!-- Sudah pernah ganti: form disembunyikan, tampil info terkunci -->
      <div
        v-if="!bisaGanti && !wajib"
        class="rounded-2xl bg-white p-6 text-center shadow-lg shadow-slate-900/10"
      >
        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100">
          <AppIcon name="lock" :size="28" class="text-slate-400" />
        </span>
        <h2 class="mt-4 text-lg font-bold text-slate-900">PIN Sudah Diganti</h2>
        <p class="mt-2 text-sm text-slate-500">
          Untuk menjaga keamanan, PIN hanya bisa diganti satu kali saat login pertama.
          Hubungi petugas bila Anda lupa PIN.
        </p>
        <NuxtLink to="/siswa/profil" class="btn-primary mt-6">
          <AppIcon name="chevron-left" :size="20" />
          <span>Kembali ke Profil</span>
        </NuxtLink>
      </div>

      <div v-else class="rounded-2xl bg-white p-6 shadow-lg shadow-slate-900/10">
        <h2 class="text-lg font-bold text-slate-900">PIN Baru</h2>
        <p class="mt-1 text-sm text-slate-400">Minimal 4 karakter dan mudah Anda ingat.</p>

        <form class="mt-6 space-y-4" novalidate @submit.prevent="onSubmit">
          <!-- PIN saat ini -->
          <div>
            <label for="current_pin" class="mb-1.5 block text-sm font-semibold text-slate-700">
              PIN Saat Ini
            </label>
            <div class="relative">
              <AppIcon
                name="lock"
                :size="20"
                class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400"
              />
              <input
                id="current_pin"
                v-model="form.current_pin"
                :type="showPin ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="PIN yang dipakai sekarang"
                class="field pr-12"
              />
              <button
                type="button"
                class="absolute top-1/2 right-2 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition active:bg-slate-100"
                :aria-label="showPin ? 'Sembunyikan PIN' : 'Lihat PIN'"
                @click="showPin = !showPin"
              >
                <AppIcon :name="showPin ? 'eye-off' : 'eye'" :size="20" />
              </button>
            </div>
          </div>

          <!-- PIN baru -->
          <div>
            <label for="pin" class="mb-1.5 block text-sm font-semibold text-slate-700">
              PIN Baru
            </label>
            <div class="relative">
              <AppIcon
                name="lock"
                :size="20"
                class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400"
              />
              <input
                id="pin"
                v-model="form.pin"
                :type="showPin ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="minimal 4 karakter"
                class="field"
              />
            </div>
          </div>

          <!-- Konfirmasi PIN baru -->
          <div>
            <label for="pin_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">
              Konfirmasi PIN Baru
            </label>
            <div class="relative">
              <AppIcon
                name="lock"
                :size="20"
                class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400"
              />
              <input
                id="pin_confirmation"
                v-model="form.pin_confirmation"
                :type="showPin ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="ulangi PIN baru"
                class="field"
              />
            </div>
            <p
              v-if="form.pin_confirmation.length > 0 && form.pin !== form.pin_confirmation"
              class="mt-2 text-xs font-medium text-danger-600"
            >
              Konfirmasi PIN belum sama.
            </p>
          </div>

          <p v-if="errorMessage" class="text-sm font-medium text-danger-600" role="alert">
            {{ errorMessage }}
          </p>

          <button type="submit" class="btn-primary mt-2" :disabled="isLoading || !bisaSubmit">
            <span>{{ isLoading ? 'Menyimpan…' : 'Simpan PIN' }}</span>
            <AppIcon v-if="!isLoading" name="check" :size="20" />
            <span
              v-else
              class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
            />
          </button>
        </form>
      </div>

      <button
        type="button"
        class="mt-6 w-full text-center text-xs font-semibold text-slate-400 active:text-slate-600"
        @click="onKeluar"
      >
        Keluar dari akun
      </button>
    </div>
  </div>
</template>
