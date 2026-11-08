<template>
  <div class="flex flex-col overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
    <!-- Toolbar Atas Tabel -->
    <div
      class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 xl:flex-row xl:items-center xl:justify-between"
    >
      <!-- Sisi Kiri (Search Bar + Filter) -->
      <div class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center xl:w-auto">
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

        <!-- Filter jurusan -->
        <div class="w-full sm:w-56">
          <select
            v-model="selectedMajorId"
            class="input"
            aria-label="Filter jurusan"
            data-testid="filter-jurusan"
          >
            <option :value="null">Semua Jurusan</option>
            <option v-for="major in majorOptions" :key="major.id" :value="major.id">
              {{ major.code }} — {{ major.name }}
            </option>
          </select>
        </div>

        <!-- Filter kelas (menyesuaikan jurusan yang dipilih) -->
        <div class="w-full sm:w-44">
          <select
            v-model="selectedClassroomId"
            class="input"
            aria-label="Filter kelas"
            data-testid="filter-kelas"
          >
            <option :value="null">Semua Kelas</option>
            <option v-for="room in classroomOptions" :key="room.id" :value="room.id">
              {{ room.name }}
            </option>
          </select>
        </div>

        <button
          v-if="filterAktif"
          type="button"
          class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100"
          data-testid="reset-filter"
          @click="resetFilter"
        >
          <X class="h-3.5 w-3.5" />
          Reset
        </button>
      </div>

      <!-- Sisi Kanan (Tombol Aksi) -->
      <div class="flex w-full flex-wrap items-center gap-3 xl:w-auto">
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
                  class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-blue-900 text-white transition-colors hover:bg-blue-800"
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
              Tidak ada data yang cocok dengan pencarian atau filter.
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
      <div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="flex flex-col gap-1.5">
            <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
              <UserRound class="h-4 w-4 text-blue-700" />
              Nama Lengkap
            </span>
            <!-- Otomatis huruf kapital saat diketik -->
            <input
              :value="form.full_name"
              type="text"
              class="input uppercase"
              placeholder="Nama siswa"
              autocomplete="off"
              @input="onUpperInput($event, 'full_name')"
            />
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
              <Hash class="h-4 w-4 text-blue-700" />
              NIS
            </span>
            <!-- Hanya angka, maksimal 4 digit; bukan type=number agar tanpa panah & tetap menyimpan angka 0 di depan -->
            <input
              :value="form.nis"
              type="text"
              inputmode="numeric"
              pattern="[0-9]*"
              :maxlength="NIS_MAKS"
              autocomplete="off"
              class="input font-mono"
              :class="nisError ? 'input-error' : ''"
              placeholder="Maks. 4 angka"
              data-testid="input-nis"
              @input="onNisInput"
            />
            <span v-if="nisError" class="text-xs font-medium text-red-600" role="alert">
              {{ nisError }}
            </span>
            <span v-else-if="nisChecking" class="text-xs text-slate-400">Memeriksa NIS...</span>
            <span v-else class="text-xs text-slate-400">
              Hanya angka, maksimal 4 digit, tidak boleh sama dengan NIS lain.
            </span>
          </label>

          <label class="flex flex-col gap-1.5 sm:col-span-2">
            <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
              <School class="h-4 w-4 text-blue-700" />
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
              <CalendarRange class="h-4 w-4 text-blue-700" />
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
              <UsersRound class="h-4 w-4 text-blue-700" />
              Nama Wali
            </span>
            <!-- Otomatis huruf kapital saat diketik -->
            <input
              :value="form.guardian_name"
              type="text"
              class="input uppercase"
              placeholder="Nama orang tua / wali"
              autocomplete="off"
              @input="onUpperInput($event, 'guardian_name')"
            />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="flex items-center gap-2 text-sm font-medium text-slate-800">
              <HeartHandshake class="h-4 w-4 text-blue-700" />
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
              <Phone class="h-4 w-4 text-blue-700" />
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

        <!-- Pesan error di dalam modal (supaya tidak tersembunyi di belakang layar gelap) -->
        <p
          v-if="formError"
          class="mt-4 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
          role="alert"
        >
          <TriangleAlert class="h-4 w-4 shrink-0" />
          {{ formError }}
        </p>
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
  X,
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

/* ---------- Filter jurusan & kelas ---------- */
const selectedMajorId = ref<number | null>(null)
const selectedClassroomId = ref<number | null>(null)

/** Daftar jurusan diambil dari data kelas. */
const majorOptions = computed(() => {
  const map = new Map<number, { id: number; code: string; name: string }>()
  for (const room of classrooms.value) {
    if (room.major) map.set(room.major.id, room.major)
  }
  return [...map.values()].sort((a, b) => a.code.localeCompare(b.code))
})

/** Pilihan kelas menyesuaikan jurusan yang dipilih (semua kelas bila jurusan belum dipilih). */
const classroomOptions = computed(() =>
  selectedMajorId.value === null
    ? classrooms.value
    : classrooms.value.filter((room) => room.major?.id === selectedMajorId.value),
)

const filterAktif = computed(
  () => selectedMajorId.value !== null || selectedClassroomId.value !== null,
)

const resetFilter = () => {
  selectedMajorId.value = null
  selectedClassroomId.value = null
}

/** Jumlah data yang diminta per kelas saat memfilter jurusan tanpa kelas. */
const PER_KELAS = 100

/** Penanda permintaan terbaru; hasil permintaan lama diabaikan. */
let loadId = 0

const loadStudents = async () => {
  const requestId = ++loadId
  loading.value = true

  try {
    const search = searchQuery.value.trim() || undefined

    // Tanpa filter, atau kelas dipilih: satu permintaan, paginasi dari server.
    if (selectedClassroomId.value !== null || selectedMajorId.value === null) {
      const res = await api.students({
        search,
        classroom_id: selectedClassroomId.value ?? undefined,
        page: currentPage.value,
        per_page: perPage,
      })
      if (requestId !== loadId) return

      students.value = res.data
      totalData.value = res.meta.total
      totalPages.value = res.meta.last_page
      return
    }

    // Jurusan dipilih tanpa kelas: backend belum punya filter jurusan, jadi
    // siswa tiap kelas jurusan itu dimuat lalu digabung dan dipaginasi di sini.
    const results = await Promise.all(
      classroomOptions.value.map((room) =>
        api.students({ search, classroom_id: room.id, page: 1, per_page: PER_KELAS }),
      ),
    )
    if (requestId !== loadId) return

    const merged = results
      .flatMap((res) => res.data)
      .sort((a, b) => a.full_name.localeCompare(b.full_name, 'id'))

    totalData.value = merged.length
    totalPages.value = Math.max(1, Math.ceil(merged.length / perPage))

    const awal = (currentPage.value - 1) * perPage
    students.value = merged.slice(awal, awal + perPage)
  } catch (error) {
    if (requestId === loadId) showNotice('error', apiErrorMessage(error))
  } finally {
    if (requestId === loadId) loading.value = false
  }
}

const loadClassrooms = async () => {
  try {
    const res = await api.classrooms()
    classrooms.value = res.data
  } catch {
    // Daftar kelas opsional untuk form & filter; abaikan bila gagal.
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

// Ganti jurusan: kelas yang tidak termasuk jurusan itu dilepas.
watch(selectedMajorId, () => {
  const kelasDipilih = selectedClassroomId.value
  if (kelasDipilih !== null && !classroomOptions.value.some((room) => room.id === kelasDipilih)) {
    selectedClassroomId.value = null
  }
})

// Filter berubah: kembali ke halaman 1, lalu muat ulang.
watch([selectedMajorId, selectedClassroomId], () => {
  if (currentPage.value !== 1) {
    currentPage.value = 1 // watch(currentPage) yang memuat ulang
  } else {
    loadStudents()
  }
})

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

/** NIS: hanya angka, 1–4 digit. Disimpan sebagai teks supaya angka 0 di depan (mis. 0999) tidak hilang. */
const NIS_MAKS = 4
const NIS_FORMAT = /^\d{1,4}$/

const nisError = ref('')
const nisChecking = ref(false)
const formError = ref('')

let nisTimer: ReturnType<typeof setTimeout> | null = null
/** Penanda pemeriksaan terbaru; hasil pemeriksaan lama diabaikan. */
let nisCheckId = 0

const nisSudahTerdaftar = (nis: string) => `NIS ${nis} sudah terdaftar. Gunakan NIS lain.`

/** Rapikan nama: spasi ganda dihapus, semua huruf kapital. */
const normalisasiNama = (value: string) => value.replace(/\s+/g, ' ').trim().toUpperCase()

/** Ubah isi kolom nama jadi huruf kapital saat diketik, kursor tetap di tempatnya. */
const onUpperInput = (event: Event, field: 'full_name' | 'guardian_name') => {
  const el = event.target as HTMLInputElement
  const start = el.selectionStart
  const end = el.selectionEnd
  const upper = el.value.toUpperCase()

  el.value = upper
  if (start !== null && end !== null) el.setSelectionRange(start, end)
  form[field] = upper
}

/** Cek ke server apakah NIS sudah dipakai. Mengembalikan true bila NIS masih bebas. */
const checkNis = async (nis: string): Promise<boolean> => {
  const checkId = ++nisCheckId
  nisChecking.value = true

  try {
    const res = await api.students({ search: nis, per_page: 20 })
    if (checkId !== nisCheckId) return true // sudah ada pemeriksaan yang lebih baru

    const sudahAda = res.data.some((student) => student.nis === nis)
    nisError.value = sudahAda ? nisSudahTerdaftar(nis) : ''
    return !sudahAda
  } catch {
    // Pemeriksaan cepat gagal: validasi server saat Simpan tetap jadi penentu akhir.
    return true
  } finally {
    if (checkId === nisCheckId) nisChecking.value = false
  }
}

const onNisInput = (event: Event) => {
  const el = event.target as HTMLInputElement
  const digits = el.value.replace(/\D/g, '').slice(0, NIS_MAKS)

  // Isi kolom ditulis ulang langsung supaya huruf/simbol tidak sempat tampil.
  el.value = digits
  form.nis = digits

  if (nisTimer) clearTimeout(nisTimer)
  nisCheckId++ // batalkan pemeriksaan yang sedang berjalan
  nisChecking.value = false
  nisError.value = ''

  if (!digits) return
  nisTimer = setTimeout(() => checkNis(digits), 400)
}

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

  if (nisTimer) clearTimeout(nisTimer)
  nisCheckId++
  nisChecking.value = false
  nisError.value = ''
  formError.value = ''
  isFormOpen.value = true
}

const submitStudent = async () => {
  formError.value = ''

  const nis = form.nis.trim()
  const fullName = normalisasiNama(form.full_name)

  if (!fullName) {
    formError.value = 'Nama lengkap wajib diisi.'
    return
  }

  if (!NIS_FORMAT.test(nis)) {
    nisError.value = 'NIS harus berupa angka, maksimal 4 digit.'
    return
  }

  savingStudent.value = true
  try {
    if (nisTimer) clearTimeout(nisTimer)

    // Pemeriksaan terakhir sebelum menyimpan.
    if (!(await checkNis(nis))) return

    const phone = form.guardian_phone.trim()
    const guardianName = normalisasiNama(form.guardian_name)

    await api.createStudent({
      nis,
      full_name: fullName,
      classroom_id: form.classroom_id,
      academic_year_id: form.academic_year_id,
      // Wali hanya dikirim bila nomornya diisi.
      ...(phone
        ? {
            guardians: [
              {
                name: guardianName || null,
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
    // Server menolak NIS (mis. ganda): tampilkan di bawah kolom NIS dalam bahasa Indonesia.
    const nisErrors = (error as { data?: { errors?: Record<string, string[]> } })?.data?.errors?.nis
    if (nisErrors?.length) {
      const pesan = nisErrors[0] ?? ''
      nisError.value = /taken|sudah|unique|duplicate/i.test(pesan) ? nisSudahTerdaftar(nis) : pesan
      return
    }

    formError.value = apiErrorMessage(error)
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
  if (nisTimer) clearTimeout(nisTimer)
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
/* Kolom bermasalah (mis. NIS ganda): garis merah */
.input.input-error,
.input.input-error:focus {
  border-color: #dc2626;
}
</style>