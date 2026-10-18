<template>
  <div class="grid w-full grid-cols-1 md:grid-cols-2">
    <!-- Kolom Kiri: Form -->
    <section class="flex items-center justify-center bg-white px-6 py-10 sm:px-12">
      <div class="w-full max-w-md">
        <div class="mb-10 flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-900">
            <ShieldCheck class="h-5 w-5 text-white" />
          </div>
          <div class="flex flex-col">
            <span class="text-base font-bold leading-tight text-slate-800">Presensi</span>
            <span class="text-xs leading-tight text-gray-500">Panel Petugas</span>
          </div>
        </div>

        <div class="mb-8">
          <h1 class="text-3xl font-semibold tracking-tight text-slate-800">Masuk Petugas</h1>
          <p class="mt-2 text-sm text-gray-500">
            Jalankan mesin absensi dan pantau kehadiran siswa.
          </p>
        </div>

        <form class="flex flex-col gap-5" @submit.prevent="handleLogin">
          <base-input
            v-model="form.email"
            label="Email / Username"
            type="text"
            placeholder="Masukkan email atau username"
            autocomplete="username"
            required
          />

          <base-input
            v-model="form.password"
            label="Password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Masukkan password"
            autocomplete="current-password"
            required
          >
            <template #suffix>
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition-colors hover:text-slate-800 focus:outline-none"
                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                @click="showPassword = !showPassword"
              >
                <EyeOff v-if="showPassword" class="h-4 w-4" />
                <Eye v-else class="h-4 w-4" />
              </button>
            </template>
          </base-input>

          <p
            v-if="errorMessage"
            class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            role="alert"
          >
            {{ errorMessage }}
          </p>

          <base-button type="submit" variant="solid" class="mt-2 w-full" :loading="isLoading">
            {{ isLoading ? 'Memproses...' : 'Masuk' }}
          </base-button>
        </form>
      </div>
    </section>

    <!-- Kolom Kanan: Visual -->
    <section class="relative hidden bg-emerald-950 md:block">
      <img
        src="/images/school-building.svg"
        alt="Ilustrasi gedung sekolah"
        class="absolute inset-0 h-full w-full object-cover"
      />
      <div class="absolute inset-0 bg-black/40" />
      <div class="relative z-10 flex h-full flex-col justify-end px-10 pb-12 lg:px-14 lg:pb-16">
        <h2 class="max-w-md text-3xl font-semibold leading-tight tracking-tight text-white lg:text-4xl">
          Operasional Absensi dalam Satu Panel
        </h2>
        <p class="mt-4 max-w-md text-sm leading-relaxed text-white/70">
          Jalankan mesin absensi, scan wajah siswa, dan pantau rekap kehadiran
          langsung dari perangkatmu.
        </p>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { Eye, EyeOff, ShieldCheck } from '@lucide/vue'
import { apiErrorMessage } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'auth',
})

const { login, logout } = useAuth()

const form = reactive({ email: '', password: '' })
const showPassword = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
  if (isLoading.value) return

  isLoading.value = true
  errorMessage.value = ''

  try {
    const user = await login(form.email, form.password)

    // Panel ini khusus petugas; admin memakai aplikasi admin (:3000).
    if (user.role !== 'Petugas') {
      await logout()
      errorMessage.value = 'Akun ini bukan akun petugas. Silakan masuk lewat panel admin.'
      return
    }

    await navigateTo('/petugas', { replace: true })
  } catch (error) {
    errorMessage.value = apiErrorMessage(error)
  } finally {
    isLoading.value = false
  }
}
</script>
