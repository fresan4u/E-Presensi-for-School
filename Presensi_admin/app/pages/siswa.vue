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
          placeholder="Cari nama, NIS, atau kelas..."
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
        <input
          ref="fileInput"
          type="file"
          accept=".csv,text/csv"
          class="hidden"
          @change="onFileChange"
        />

        <base-button
          variant="outline"
          :loading="importing"
          class="whitespace-nowrap px-3 py-2 text-xs"
          @click="fileInput?.click()"
        >
          <FileSpreadsheet class="h-4 w-4 shrink-0" />
          Import CSV
        </base-button>

        <base-button
          variant="solid"
          data-testid="tambah-siswa"
          class="whitespace-nowrap px-3 py-2 text-xs"
          @click="openCreate"
        >
          <UserPlus class="h-4 w-4 shrink-0" />
          Tambah Siswa
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
            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
              <span class="inline-flex items-center gap-2">
                <Loader2 class="h-4 w-4 animate-spin" />
                Memuat data siswa...
              </span>
            </td>
          </tr>

          <tr
            v-for="siswa in students"
            v-else
            :key="siswa.id"
            class="border-b border-gray-100 transition-colors last:border-0 hover:bg-slate-50/60"
          >
            <td class="px-6 py-4 text-sm font-medium text-slate-800">
              {{ siswa.full_name }}
            </td>
            <td class="px-6 py-4 text-sm text-gray-600">{{ siswa.nis }}</td>
            <td class="px-6 py-4">
              <span class="font-mono text-sm text-slate-800">{{ siswa.pin ?? '-' }}</span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600">
              {{ siswa.classroom?.name ?? '-' }}
            </td>
            <td class="px-6 py-4 text-sm text-gray-600">
              {{ siswa.academic_year?.label ?? '-' }}
            </td>
            <td class="px-6 py-4">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="(siswa.face_descriptors_count ?? 0) >= maxSamples
                  ? 'bg-emerald-50 text-emerald-800'
                  : 'bg-amber-50 text-amber-800'"
              >
                {{ siswa.face_descriptors_count ?? 0 }}/{{ maxSamples }} sampel
              </span>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center justify-end gap-2">
                <button
                  type="button"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-slate-700 transition-colors hover:bg-slate-200"
                  :aria-label="`Ubah PIN ${siswa.full_name}`"
                  title="Ubah PIN"
                  @click="openPin(siswa)"
                >
                  <KeyRound class="h-4 w-4" />
                </button>
                <nuxt-link
                  :to="{ path: '/registrasi-wajah', query: { student: siswa.id } }"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-emerald-900 text-white transition-colors hover:bg-emerald-800"
                  :aria-label="`Daftarkan wajah ${siswa.full_name}`"
                  title="Daftarkan wajah"
                >
                  <ScanFace class="h-4 w-4" />
                </nuxt-link>
                <button
                  type="button"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                  :aria-label="`Hapus ${siswa.full_name}`"
                  title="Hapus siswa"
                  @click="removeStudent(siswa)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>

          <!-- Empty state -->
          <tr v-if="!loading && !students.length">
            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
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

    <!-- Modal Tambah Siswa -->
    <modal
      v-model="isFormOpen"
      title="Tambah Siswa"
      description="Isi data pokok siswa lalu simpan."
      :loading="savingStudent"
      @confirm="submitStudent"
    >
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5">
          <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
            <UserRound class="h-4 w-4 text-emerald-700" />
            Nama Lengkap
          </span>
          <input v-model="form.full_name" type="text" class="input" placeholder="Nama siswa" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
            <Hash class="h-4 w-4 text-emerald-700" />
            NIS
          </span>
          <input v-model="form.nis" type="text" class="input" placeholder="Nomor induk siswa" />
        </label>
        <label class="flex flex-col gap-1.5 sm:col-span-2">
          <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
            <School class="h-4 w-4 text-emerald-700" />
            Kelas
          </span>
          <select v-model="form.classroom_id" class="input">
            <option :value="null">Belum ditentukan</option>
            <option v-for="room in classrooms" :key="room.id" :value="room.id">
              {{ room.name }}
            </option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5 sm:col-span-2">
          <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
            <CalendarRange class="h-4 w-4 text-emerald-700" />
            Tahun Ajaran
          </span>
          <select v-model="form.academic_year_id" class="input" :disabled="loadingAcademicYears">
            <option :value="null">Belum ditentukan</option>
            <option v-for="year in academicYears" :key="year.id" :value="year.id">
              {{ year.label }}{{ year.is_active ? ' (Aktif)' : '' }}
            </option>
          </select>
          <span v-if="loadingAcademicYears" class="text-xs text-slate-400">Memuat tahun ajaran...</span>
        </label>

        <!-- Nomor wali murid: dipakai WaNotifier untuk kirim notifikasi absensi -->
        <label class="flex flex-col gap-1.5">
          <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
            <UsersRound class="h-4 w-4 text-emerald-700" />
            Nama Wali
          </span>
          <input
            v-model="form.guardian_name"
            type="text"
            class="input"
            placeholder="Nama orang tua / wali"
          />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
            <HeartHandshake class="h-4 w-4 text-emerald-700" />
            Hubungan
          </span>
          <select v-model="form.guardian_relationship" class="input">
            <option value="ayah">Ayah</option>
            <option value="ibu">Ibu</option>
            <option value="wali">Wali</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5 sm:col-span-2">
          <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
            <Phone class="h-4 w-4 text-emerald-700" />
            Nomor WhatsApp Wali
          </span>
          <input
            v-model="form.guardian_phone"
            type="tel"
            class="input"
            placeholder="08xxxxxxxxxx"
          />
          <span class="text-xs text-slate-400">
            Kosongkan bila belum ada. Nomor ini menerima notifikasi absensi (teks + foto).
          </span>
        </label>
      </div>
    </modal>

    <!-- Modal Ubah PIN -->
    <modal
      v-model="isPinOpen"
      :title="pinStudent ? `Ubah PIN ${pinStudent.full_name}` : 'Ubah PIN Siswa'"
      description="PIN baru langsung berlaku untuk login aplikasi mobile siswa."
      :loading="savingPin"
      @confirm="submitPin"
    >
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium text-slate-800">PIN Baru</span>
        <input
          v-model="pinForm.pin"
          type="text"
          inputmode="numeric"
          autocomplete="off"
          class="input font-mono"
          placeholder="Minimal 4 karakter"
        />
        <span class="text-xs text-slate-400">
          Sampaikan PIN ini hanya kepada siswa yang bersangkutan.
        </span>
      </label>
    </modal>
  </div>
</template>

<script setup lang="ts">
import {
  CheckCircle2,
  CalendarRange,
  FileSpreadsheet,
  Hash,
  HeartHandshake,
  KeyRound,
  Loader2,
  Phone,
  School,
  ScanFace,
  Search,
  Trash2,
  TriangleAlert,
  UserPlus,
  UserRound,
  UsersRound,
} from '@lucide/vue'
import type { ApiAcademicYear, ApiClassroom, ApiStudent } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'default',
  title: 'Master Data Siswa',
})

const api = useApi()
const maxSamples = 3
const perPage = 10

const searchQuery = ref('')
const currentPage = ref(1)
const loading = ref(false)
const students = ref<ApiStudent[]>([])
const totalData = ref(0)
const totalPages = ref(1)

const classrooms = ref<ApiClassroom[]>([])
const academicYears = ref<ApiAcademicYear[]>([])
const loadingAcademicYears = ref(false)
const notice = ref<{ type: 'success' | 'error'; text: string } | null>(null)

const tableHeaders = ['Nama Lengkap', 'NIS', 'PIN', 'Kelas', 'Tahun Ajaran', 'Wajah']

let searchTimer: ReturnType<typeof setTimeout> | null = null

const startRange = computed(() => (totalData.value === 0 ? 0 : (currentPage.value - 1) * perPage + 1))
const endRange = computed(() => Math.min(currentPage.value * perPage, totalData.value))

const showNotice = (type: 'success' | 'error', text: string) => {
  notice.value = { type, text }
}

const loadStudents = async () => {
  loading.value = true
  try {
    const res = await api.students({
      search: searchQuery.value.trim() || undefined,
      page: currentPage.value,
      per_page: perPage,
    })
    students.value = res.data
    totalData.value = res.meta.total
    totalPages.value = res.meta.last_page
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    loading.value = false
  }
}

const loadClassrooms = async () => {
  try {
    const res = await api.classrooms()
    classrooms.value = res.data
  } catch {
    // Daftar kelas opsional untuk form; abaikan bila gagal.
  }
}

const loadAcademicYears = async () => {
  loadingAcademicYears.value = true
  try {
    const res = await api.academicYears()
    academicYears.value = res.data
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    loadingAcademicYears.value = false
  }
}

watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadStudents()
  }, 300)
})

watch(currentPage, loadStudents)

/* ---------- Import CSV ---------- */
const fileInput = ref<HTMLInputElement | null>(null)
const importing = ref(false)

const onFileChange = async (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  importing.value = true
  try {
    const res = await api.importStudents(file)
    const { created, updated, errors } = res.data
    const parts = [`${created} siswa baru`, `${updated} diperbarui`]
    if (errors.length) parts.push(`${errors.length} baris bermasalah`)
    showNotice('success', `Import selesai: ${parts.join(', ')}.`)
    currentPage.value = 1
    await loadStudents()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    importing.value = false
    input.value = ''
  }
}

/* ---------- Tambah / Hapus ---------- */
const isFormOpen = ref(false)
const savingStudent = ref(false)
const form = reactive({
  nis: '',
  full_name: '',
  classroom_id: null as number | null,
  academic_year_id: null as number | null,
  guardian_name: '',
  guardian_relationship: 'ayah',
  guardian_phone: '',
})

const openCreate = () => {
  Object.assign(form, {
    nis: '',
    full_name: '',
    classroom_id: null,
    academic_year_id: academicYears.value.find((year) => year.is_active)?.id ?? null,
    guardian_name: '',
    guardian_relationship: 'ayah',
    guardian_phone: '',
  })
  isFormOpen.value = true
}

const submitStudent = async () => {
  savingStudent.value = true
  try {
    const phone = form.guardian_phone.trim()

    await api.createStudent({
      nis: form.nis.trim(),
      full_name: form.full_name.trim(),
      classroom_id: form.classroom_id,
      academic_year_id: form.academic_year_id,
      // Wali hanya dikirim bila nomornya diisi.
      ...(phone
        ? {
            guardians: [
              {
                name: form.guardian_name.trim() || null,
                relationship: form.guardian_relationship,
                phone,
                is_primary: true,
              },
            ],
          }
        : {}),
    })
    isFormOpen.value = false
    showNotice('success', 'Siswa baru berhasil ditambahkan.')
    currentPage.value = 1
    await loadStudents()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    savingStudent.value = false
  }
}

/* ---------- Ubah PIN ---------- */
const isPinOpen = ref(false)
const savingPin = ref(false)
const pinStudent = ref<ApiStudent | null>(null)
const pinForm = reactive({ pin: '' })

const openPin = (student: ApiStudent) => {
  pinStudent.value = student
  pinForm.pin = ''
  isPinOpen.value = true
}

const submitPin = async () => {
  if (!pinStudent.value) return

  const pin = pinForm.pin.trim()
  if (pin.length < 4) {
    showNotice('error', 'PIN minimal 4 karakter.')
    return
  }

  savingPin.value = true
  try {
    await api.updateStudent(pinStudent.value.id, { pin })
    isPinOpen.value = false
    showNotice('success', `PIN ${pinStudent.value.full_name} berhasil diubah.`)
    await loadStudents()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    savingPin.value = false
  }
}

const removeStudent = async (student: ApiStudent) => {
  if (!window.confirm(`Hapus data ${student.full_name}? Tindakan ini tidak bisa dibatalkan.`)) return

  try {
    await api.deleteStudent(student.id)
    showNotice('success', `${student.full_name} dihapus.`)
    await loadStudents()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  }
}

onMounted(async () => {
  await Promise.all([loadStudents(), loadClassrooms(), loadAcademicYears()])
})

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
