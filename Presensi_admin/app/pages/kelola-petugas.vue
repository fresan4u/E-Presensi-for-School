<template>
  <div class="flex flex-col overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
    <!-- Toolbar Atas Tabel -->
    <div
      class="flex flex-col items-start justify-between gap-4 border-b border-gray-100 px-6 py-5 sm:flex-row sm:items-center"
    >
      <!-- Sisi Kiri (Search Bar) -->
      <div class="w-full sm:w-72 lg:w-80">
        <base-input
          v-model="searchQuery"
          type="search"
          placeholder="Cari nama atau email petugas..."
        >
          <template #prefix>
            <Search
              class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
            />
          </template>
        </base-input>
      </div>

      <!-- Sisi Kanan (Tombol Aksi) -->
      <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto">
        <base-button variant="solid" data-testid="tambah-petugas" @click="openCreate">
          <Plus class="h-4 w-4" />
          Tambah Petugas
        </base-button>
      </div>
    </div>

    <!-- Notifikasi hasil aksi -->
    <p
      v-if="notice"
      class="mx-6 mt-4 flex items-center gap-2 rounded-md border px-4 py-3 text-sm"
      :class="notice.type === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : 'border-red-200 bg-red-50 text-red-700'"
      role="status"
    >
      <component :is="notice.type === 'success' ? CheckCircle2 : TriangleAlert" class="h-4 w-4 shrink-0" />
      {{ notice.text }}
    </p>

    <!-- Tabel Utama -->
    <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead class="bg-slate-50/80">
          <tr>
            <th
              v-for="header in tableHeaders"
              :key="header"
              class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-slate-800"
            >
              {{ header }}
            </th>
            <th
              class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-800"
            >
              Aksi
            </th>
          </tr>
        </thead>
        <tbody class="bg-white">
          <tr v-if="loading">
            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
              <span class="inline-flex items-center gap-2">
                <Loader2 class="h-4 w-4 animate-spin" />
                Memuat data petugas...
              </span>
            </td>
          </tr>

          <tr
            v-for="officer in officers"
            v-else
            :key="officer.id"
            class="border-b border-gray-100 transition-colors last:border-0 hover:bg-slate-50/60"
          >
            <td class="px-6 py-4 text-sm font-medium text-slate-800">
              {{ officer.name }}
            </td>
            <td class="px-6 py-4 text-sm text-gray-600">{{ officer.email }}</td>
            <td class="px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="officer.is_active
                  ? 'bg-emerald-50 text-emerald-800'
                  : 'bg-slate-100 text-slate-600'"
              >
                {{ officer.is_active ? 'Aktif' : 'Non Aktif' }}
              </span>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center justify-end gap-2">
                <button
                  type="button"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                  :aria-label="`Ubah ${officer.name}`"
                  title="Ubah petugas"
                  @click="openEdit(officer)"
                >
                  <Pencil class="h-4 w-4" />
                </button>
                <button
                  type="button"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors"
                  :class="officer.is_active
                    ? 'text-amber-600 hover:bg-amber-50'
                    : 'text-emerald-700 hover:bg-emerald-50'"
                  :aria-label="officer.is_active ? `Nonaktifkan ${officer.name}` : `Aktifkan ${officer.name}`"
                  :title="officer.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                  @click="toggleActive(officer)"
                >
                  <Power class="h-4 w-4" />
                </button>
                <button
                  type="button"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                  :aria-label="`Hapus ${officer.name}`"
                  title="Hapus petugas"
                  @click="removeOfficer(officer)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>

          <!-- Empty state -->
          <tr v-if="!loading && !officers.length">
            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
              Tidak ada data yang cocok dengan pencarianmu.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div
      class="flex flex-col items-start justify-between gap-4 border-t border-gray-100 px-6 py-5 sm:flex-row sm:items-center"
    >
      <p class="text-sm text-gray-500">
        Menampilkan {{ startRange }} sampai {{ endRange }} dari {{ totalData }} data
      </p>
      <pagination v-model:current-page="currentPage" :total-pages="totalPages" />
    </div>

    <!-- Modal Tambah / Ubah Petugas -->
    <modal
      v-model="isFormOpen"
      :title="editingId ? 'Ubah Petugas' : 'Tambah Petugas'"
      :description="editingId ? 'Perbarui data akun petugas.' : 'Buat akun petugas baru.'"
      :loading="savingOfficer"
      @confirm="submitOfficer"
    >
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5">
          <span class="text-sm font-medium text-slate-800">Nama Lengkap</span>
          <input v-model="form.name" type="text" class="input" placeholder="Nama petugas" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-sm font-medium text-slate-800">Email</span>
          <input v-model="form.email" type="email" class="input" placeholder="nama@sekolah.id" />
        </label>
        <label class="flex flex-col gap-1.5 sm:col-span-2">
          <span class="text-sm font-medium text-slate-800">Kata Sandi</span>
          <input
            v-model="form.password"
            type="text"
            class="input"
            :placeholder="editingId ? 'Kosongkan bila tidak diubah' : 'Minimal 6 karakter'"
          />
        </label>
        <label class="flex items-center gap-2.5 sm:col-span-2">
          <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-emerald-800" />
          <span class="text-sm font-medium text-slate-800">Akun aktif (bisa login)</span>
        </label>
      </div>
    </modal>
  </div>
</template>

<script setup lang="ts">
import { CheckCircle2, Loader2, Pencil, Plus, Power, Search, Trash2, TriangleAlert } from '@lucide/vue'
import type { ApiUser, OfficerPayload } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'default',
  title: 'Kelola Petugas',
})

const api = useApi()
const perPage = 10

const searchQuery = ref('')
const currentPage = ref(1)
const loading = ref(false)
const officers = ref<ApiUser[]>([])
const totalData = ref(0)
const totalPages = ref(1)

const notice = ref<{ type: 'success' | 'error'; text: string } | null>(null)

const tableHeaders = ['Nama', 'Email', 'Status']

let searchTimer: ReturnType<typeof setTimeout> | null = null

const startRange = computed(() => (totalData.value === 0 ? 0 : (currentPage.value - 1) * perPage + 1))
const endRange = computed(() => Math.min(currentPage.value * perPage, totalData.value))

const showNotice = (type: 'success' | 'error', text: string) => {
  notice.value = { type, text }
}

const loadOfficers = async () => {
  loading.value = true
  try {
    const res = await api.officers({
      search: searchQuery.value.trim() || undefined,
      page: currentPage.value,
      per_page: perPage,
    })
    officers.value = res.data
    totalData.value = res.meta.total
    totalPages.value = res.meta.last_page
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    loading.value = false
  }
}

watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadOfficers()
  }, 300)
})

watch(currentPage, loadOfficers)

/* ---------- Tambah / Ubah / Hapus ---------- */
const isFormOpen = ref(false)
const savingOfficer = ref(false)
const editingId = ref<number | null>(null)
const form = reactive({
  name: '',
  email: '',
  password: '',
  is_active: true,
})

const openCreate = () => {
  editingId.value = null
  Object.assign(form, { name: '', email: '', password: '', is_active: true })
  isFormOpen.value = true
}

const openEdit = (officer: ApiUser) => {
  editingId.value = officer.id
  Object.assign(form, {
    name: officer.name,
    email: officer.email,
    password: '',
    is_active: officer.is_active,
  })
  isFormOpen.value = true
}

const submitOfficer = async () => {
  savingOfficer.value = true
  try {
    if (editingId.value) {
      const payload: Partial<OfficerPayload> = {
        name: form.name.trim(),
        email: form.email.trim(),
        is_active: form.is_active,
      }
      if (form.password.trim()) payload.password = form.password.trim()
      await api.updateOfficer(editingId.value, payload)
      showNotice('success', 'Data petugas berhasil diperbarui.')
    } else {
      await api.createOfficer({
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password.trim(),
        is_active: form.is_active,
      })
      showNotice('success', 'Petugas baru berhasil ditambahkan.')
      currentPage.value = 1
    }
    isFormOpen.value = false
    await loadOfficers()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    savingOfficer.value = false
  }
}

const toggleActive = async (officer: ApiUser) => {
  try {
    await api.updateOfficer(officer.id, { is_active: !officer.is_active })
    showNotice('success', `${officer.name} ${officer.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`)
    await loadOfficers()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  }
}

const removeOfficer = async (officer: ApiUser) => {
  if (!window.confirm(`Hapus akun ${officer.name}? Tindakan ini tidak bisa dibatalkan.`)) return

  try {
    await api.deleteOfficer(officer.id)
    showNotice('success', `${officer.name} dihapus.`)
    await loadOfficers()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  }
}

onMounted(loadOfficers)

onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer)
})
</script>

<style scoped>
.input {
  width: 100%;
  border-radius: 0.375rem;
  border: 1px solid #d1d5db;
  background: #fff;
  padding: 0.625rem 0.75rem;
  font-size: 0.875rem;
  color: #1e293b;
}
.input:focus {
  outline: none;
  border-color: #064e3b;
}
</style>
