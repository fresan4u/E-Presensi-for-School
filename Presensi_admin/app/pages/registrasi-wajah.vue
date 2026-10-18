<template>
  <div class="flex flex-col gap-6">
    <!-- Header + Tab -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="text-xl font-semibold tracking-tight text-slate-800">
          Data Siswa &amp; Registrasi Wajah
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          Kelola data siswa sekaligus daftarkan sampel wajahnya di satu tempat.
        </p>
      </div>

      <!-- Toggle tab -->
      <div class="flex items-center overflow-hidden rounded-md border border-gray-200 bg-white">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors"
          :class="[
            tab.value !== 'siswa' ? 'border-l border-gray-200' : '',
            activeTab === tab.value
              ? 'bg-emerald-900 text-white'
              : 'bg-white text-gray-600 hover:bg-slate-50',
          ]"
          :data-testid="tab.testid"
          @click="setTab(tab.value)"
        >
          <component :is="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- ==================== TAB 1: DATA SISWA ==================== -->
    <div
      v-if="activeTab === 'siswa'"
      class="flex flex-col overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
    >
      <!-- Toolbar -->
      <div
        class="flex flex-col items-start justify-between gap-4 border-b border-gray-100 px-6 py-5 sm:flex-row sm:items-center"
      >
        <div class="w-full sm:w-72 lg:w-80">
          <base-input
            v-model="searchQuery"
            type="search"
            placeholder="Cari nama, NIS, atau kelas..."
          >
            <template #prefix>
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            </template>
          </base-input>
        </div>

        <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto">
          <input
            ref="fileInput"
            type="file"
            accept=".csv,text/csv"
            class="hidden"
            @change="onFileChange"
          />

          <base-button variant="outline" :loading="importing" @click="fileInput?.click()">
            <Upload class="h-4 w-4" />
            Import CSV
          </base-button>

          <base-button variant="solid" data-testid="tambah-siswa" @click="openCreate">
            <Plus class="h-4 w-4" />
            Tambah Siswa
          </base-button>
        </div>
      </div>

      <!-- Notifikasi aksi CRUD -->
      <p
        v-if="tableNotice"
        class="mx-6 mt-4 flex items-center gap-2 rounded-md border px-4 py-3 text-sm"
        :class="tableNotice.type === 'success'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
          : 'border-red-200 bg-red-50 text-red-700'"
        role="status"
      >
        <component :is="tableNotice.type === 'success' ? CheckCircle2 : TriangleAlert" class="h-4 w-4 shrink-0" />
        {{ tableNotice.text }}
      </p>

      <!-- Tawaran lanjut daftarkan wajah setelah tambah -->
      <div
        v-if="justCreated"
        class="mx-6 mt-4 flex flex-col items-start justify-between gap-3 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 sm:flex-row sm:items-center"
      >
        <p class="flex items-center gap-2 text-sm text-emerald-800">
          <ScanFace class="h-4 w-4 shrink-0" />
          <span><span class="font-semibold">{{ justCreated.full_name }}</span> siap didaftarkan wajahnya.</span>
        </p>
        <base-button variant="solid" data-testid="daftarkan-wajah-baru" @click="registerFaceFor(justCreated)">
          <ScanFace class="h-4 w-4" />
          Daftarkan Wajah
        </base-button>
      </div>

      <!-- Tabel -->
      <div class="mt-4 overflow-x-auto">
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
              <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-800">
                Aksi
              </th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-if="tableLoading">
              <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                <span class="inline-flex items-center gap-2">
                  <Loader2 class="h-4 w-4 animate-spin" />
                  Memuat data siswa...
                </span>
              </td>
            </tr>

            <tr
              v-for="siswa in tableStudents"
              v-else
              :key="siswa.id"
              class="border-b border-gray-100 transition-colors last:border-0 hover:bg-slate-50/60"
            >
              <td class="px-6 py-4 text-sm font-medium text-slate-800">
                {{ siswa.full_name }}
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ siswa.nis }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">
                {{ siswa.classroom?.name ?? '-' }}
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="siswa.status?.code === 'aktif'
                    ? 'bg-emerald-50 text-emerald-800'
                    : 'bg-slate-100 text-slate-600'"
                >
                  {{ siswa.status?.label ?? '-' }}
                </span>
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
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-emerald-900 text-white transition-colors hover:bg-emerald-800"
                    :aria-label="`Daftarkan wajah ${siswa.full_name}`"
                    title="Daftarkan wajah"
                    @click="registerFaceFor(siswa)"
                  >
                    <ScanFace class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-slate-100 hover:text-slate-800"
                    :aria-label="`Edit ${siswa.full_name}`"
                    title="Edit data"
                    @click="openEdit(siswa)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
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

            <tr v-if="!tableLoading && !tableStudents.length">
              <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                Tidak ada data yang cocok. Tambah siswa baru atau ubah kata kunci.
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
    </div>

    <!-- ==================== TAB 2: REGISTRASI WAJAH ==================== -->
    <section v-else class="grid grid-cols-1 gap-4 xl:grid-cols-5">
      <!-- Kiri: Pilih Siswa -->
      <base-card class="xl:col-span-2" title="1. Pilih Siswa" subtitle="Cari lalu pilih siswa">
        <template #actions>
          <base-button variant="outline" data-testid="tambah-siswa-wajah" @click="openCreate">
            <Plus class="h-4 w-4" />
            Tambah
          </base-button>
        </template>

        <base-input v-model="pickerSearch" type="search" placeholder="Cari nama atau NIS...">
          <template #prefix>
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          </template>
        </base-input>

        <!-- Daftar siswa -->
        <div class="mt-3 flex max-h-[26rem] flex-col gap-1.5 overflow-y-auto">
          <div v-if="pickerLoading" class="flex items-center justify-center gap-2 py-8 text-sm text-gray-500">
            <Loader2 class="h-4 w-4 animate-spin" />
            Memuat data siswa...
          </div>

          <p v-else-if="!pickerStudents.length" class="py-8 text-center text-sm text-gray-500">
            Siswa tidak ditemukan.
          </p>

          <button
            v-for="student in pickerStudents"
            :key="student.id"
            type="button"
            class="flex items-center gap-3 rounded-md border px-3 py-2.5 text-left transition-colors"
            :class="selected?.id === student.id
              ? 'border-emerald-900 bg-emerald-50'
              : 'border-gray-100 bg-white hover:bg-slate-50'"
            @click="selectStudent(student)"
          >
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-sm font-semibold text-white">
              {{ student.full_name.charAt(0).toUpperCase() }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-slate-800">{{ student.full_name }}</span>
              <span class="block truncate text-xs text-gray-500">
                {{ student.nis }} · {{ student.classroom?.name ?? 'Belum ada kelas' }}
              </span>
            </span>
            <span
              class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
              :class="(student.face_descriptors_count ?? 0) >= maxSamples
                ? 'bg-emerald-100 text-emerald-800'
                : 'bg-slate-100 text-slate-600'"
            >
              {{ student.face_descriptors_count ?? 0 }}/{{ maxSamples }}
            </span>
          </button>
        </div>
      </base-card>

      <!-- Kanan: Ambil Sampel -->
      <base-card class="xl:col-span-3" title="2. Ambil Sampel Wajah" subtitle="Minimal 3 sampel per siswa">
        <!-- Belum pilih siswa -->
        <div
          v-if="!selected"
          class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-gray-200 px-6 py-16 text-center"
        >
          <UserSquare class="h-10 w-10 text-gray-300" />
          <p class="text-sm text-gray-500">Pilih siswa di sebelah kiri untuk mulai registrasi.</p>
        </div>

        <div v-else class="flex flex-col gap-4">
          <!-- Info siswa terpilih -->
          <div class="flex items-center gap-3 rounded-md bg-slate-50 px-4 py-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-900 text-base font-semibold text-white">
              {{ selected.full_name.charAt(0).toUpperCase() }}
            </span>
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-slate-800">{{ selected.full_name }}</p>
              <p class="text-xs text-gray-500">
                NIS {{ selected.nis ?? '-' }} · {{ selected.classroom?.name ?? 'Belum ada kelas' }}
              </p>
            </div>
            <div class="ml-auto flex shrink-0 items-center gap-3">
              <span class="text-xs font-medium text-emerald-800">{{ filledCount }}/{{ maxSamples }} sampel</span>
              <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-white hover:text-slate-800"
                :aria-label="`Edit data ${selected.full_name}`"
                title="Edit data siswa"
                @click="openEdit(selected)"
              >
                <Pencil class="h-4 w-4" />
              </button>
            </div>
          </div>

          <!-- Kartu sampel -->
          <div class="grid grid-cols-3 gap-2">
            <div
              v-for="n in sampleNumbers"
              :key="n"
              class="flex items-center justify-between gap-2 rounded-md border px-3 py-2.5"
              :class="samples[n] ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-white'"
            >
              <span class="flex items-center gap-2">
                <CheckCircle2 v-if="samples[n]" class="h-4 w-4 text-emerald-700" />
                <Circle v-else class="h-4 w-4 text-gray-300" />
                <span class="text-xs font-medium" :class="samples[n] ? 'text-emerald-800' : 'text-gray-500'">
                  Sampel {{ n }}
                </span>
              </span>
              <button
                v-if="samples[n]"
                type="button"
                class="rounded p-1 text-gray-400 transition-colors hover:bg-white hover:text-red-600"
                :aria-label="`Hapus sampel ${n}`"
                @click="removeSample(n)"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>

          <!-- Area kamera -->
          <div class="relative aspect-video w-full overflow-hidden rounded-lg bg-black">
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="h-full w-full -scale-x-100 object-cover"
            />

            <div
              class="absolute left-1/2 top-1/2 h-52 w-40 -translate-x-1/2 -translate-y-1/2 rounded-[50%] border-2 border-dashed"
              :class="faceStatus === 'detected' ? 'border-emerald-500' : 'border-emerald-400'"
            />

            <div
              v-if="faceStatus === 'loading'"
              class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/75 text-white"
            >
              <Loader2 class="h-7 w-7 animate-spin text-emerald-400" />
              <p class="text-sm text-gray-200">Menyiapkan kamera &amp; model...</p>
            </div>

            <div
              v-else-if="faceStatus === 'error'"
              class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black/85 px-6 text-center text-white"
            >
              <CameraOff class="h-9 w-9 text-gray-300" />
              <p class="max-w-sm text-sm text-gray-200">{{ faceError }}</p>
              <base-button variant="solid" @click="initFace">
                <RotateCcw class="h-4 w-4" />
                Coba Lagi
              </base-button>
            </div>

            <div
              v-else-if="faceStatus === 'scanning'"
              class="absolute bottom-4 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2 border border-emerald-500/40 bg-emerald-950/80 px-3 py-1.5 text-xs font-medium text-emerald-300"
            >
              <span class="flex h-2 w-2 animate-pulse rounded-full bg-emerald-400" />
              Memindai wajah...
            </div>

            <div
              v-else-if="faceStatus === 'detected'"
              class="absolute bottom-4 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2 border border-emerald-500/40 bg-emerald-950/80 px-3 py-1.5 text-xs font-medium text-emerald-300"
            >
              <CheckCircle2 class="h-3.5 w-3.5" />
              Wajah terdeteksi — siap disimpan
            </div>
          </div>

          <!-- Notifikasi -->
          <p
            v-if="notice"
            class="flex items-center gap-2 rounded-md border px-4 py-3 text-sm"
            :class="notice.type === 'success'
              ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
              : 'border-red-200 bg-red-50 text-red-700'"
            role="status"
          >
            <component :is="notice.type === 'success' ? CheckCircle2 : TriangleAlert" class="h-4 w-4 shrink-0" />
            {{ notice.text }}
          </p>

          <!-- Kontrol -->
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
              <base-button
                v-if="faceStatus !== 'scanning'"
                variant="outline"
                :disabled="faceStatus === 'loading'"
                @click="startCapture"
              >
                <ScanFace class="h-4 w-4" />
                {{ faceStatus === 'detected' ? 'Pindai Ulang' : 'Mulai Pemindaian' }}
              </base-button>
              <base-button v-else variant="outline" @click="stopFaceScan">
                Hentikan
              </base-button>
            </div>

            <base-button
              variant="solid"
              :disabled="!canSave"
              :loading="saving"
              data-testid="simpan-sampel"
              @click="saveSample"
            >
              <Save class="h-4 w-4" />
              Simpan Sampel {{ nextFreeSample ? nextFreeSample : '' }}
            </base-button>
          </div>

          <p v-if="filledCount >= maxSamples" class="text-xs text-emerald-700">
            Registrasi wajah lengkap. Sampel bisa dihapus lalu diambil ulang bila perlu.
          </p>
        </div>
      </base-card>
    </section>

    <!-- ==================== MODAL FORM SISWA ==================== -->
    <modal
      v-model="isFormOpen"
      :title="editingId ? 'Edit Siswa' : 'Tambah Siswa'"
      :description="editingId ? 'Perbarui data pokok siswa.' : 'Isi data pokok siswa lalu simpan.'"
      :loading="savingStudent"
      @confirm="submitStudent"
    >
      <p
        v-if="formError"
        class="mb-4 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        role="alert"
      >
        <TriangleAlert class="h-4 w-4 shrink-0" />
        {{ formError }}
      </p>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5">
          <span class="text-sm font-medium text-slate-800">Nama Lengkap</span>
          <input v-model="form.full_name" type="text" class="input" placeholder="Nama siswa" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-sm font-medium text-slate-800">NIS</span>
          <input v-model="form.nis" type="text" class="input" placeholder="Nomor induk siswa" />
        </label>
        <label class="flex flex-col gap-1.5 sm:col-span-2">
          <span class="text-sm font-medium text-slate-800">Kelas</span>
          <select v-model="form.classroom_id" class="input">
            <option :value="null">Belum ditentukan</option>
            <option v-for="room in classrooms" :key="room.id" :value="room.id">
              {{ room.name }}
            </option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5 sm:col-span-2">
          <span class="text-sm font-medium text-slate-800">Status</span>
          <select v-model="form.status" class="input">
            <option value="aktif">Aktif</option>
            <option value="non_aktif">Non Aktif</option>
          </select>
        </label>
      </div>
    </modal>
  </div>
</template>

<script setup lang="ts">
import {
  CameraOff,
  CheckCircle2,
  Circle,
  Loader2,
  Pencil,
  Plus,
  RotateCcw,
  Save,
  ScanFace,
  Search,
  Trash2,
  TriangleAlert,
  Upload,
  UserSquare,
  Users,
} from '@lucide/vue'
import type { ApiClassroom, ApiFaceDescriptor, ApiStudent } from '#layers/shared/app/composables/useApi'

definePageMeta({
  layout: 'default',
  title: 'Data Siswa & Registrasi Wajah',
})

type TabValue = 'siswa' | 'wajah'

const api = useApi()
const route = useRoute()

const maxSamples = 3
const sampleNumbers = [1, 2, 3]
const perPage = 10
const tableHeaders = ['Nama Lengkap', 'NIS', 'Kelas', 'Status', 'Wajah']

const tabs = [
  { value: 'siswa', label: 'Data Siswa', icon: Users, testid: 'tab-data-siswa' },
  { value: 'wajah', label: 'Registrasi Wajah', icon: ScanFace, testid: 'tab-registrasi-wajah' },
] as const

const activeTab = ref<TabValue>('siswa')
const setTab = (tab: TabValue) => {
  activeTab.value = tab
}

/* ---------- Tab 1: Data Siswa (CRUD) ---------- */
const searchQuery = ref('')
const tableStudents = ref<ApiStudent[]>([])
const tableLoading = ref(false)
const currentPage = ref(1)
const totalData = ref(0)
const totalPages = ref(1)
const classrooms = ref<ApiClassroom[]>([])
const tableNotice = ref<{ type: 'success' | 'error'; text: string } | null>(null)
const justCreated = ref<ApiStudent | null>(null)

/* ---------- Tab 2: Registrasi Wajah ---------- */
const pickerSearch = ref('')
const pickerStudents = ref<ApiStudent[]>([])
const pickerLoading = ref(false)

const selected = ref<ApiStudent | null>(null)
/** sample_no -> descriptor yang tersimpan. */
const samples = ref<Record<number, ApiFaceDescriptor>>({})
/** Descriptor yang baru terdeteksi dan siap disimpan. */
const pending = ref<number[] | null>(null)
const saving = ref(false)
const notice = ref<{ type: 'success' | 'error'; text: string } | null>(null)

const {
  videoRef,
  status: faceStatus,
  errorMessage: faceError,
  result: faceResult,
  init: initFace,
  startScan: startFaceScan,
  stopScan: stopFaceScan,
} = useFaceScanner()

const startRange = computed(() => (totalData.value === 0 ? 0 : (currentPage.value - 1) * perPage + 1))
const endRange = computed(() => Math.min(currentPage.value * perPage, totalData.value))

const filledCount = computed(() => Object.keys(samples.value).length)
const nextFreeSample = computed<number | null>(() => {
  for (const n of sampleNumbers) {
    if (!samples.value[n]) return n
  }
  return null
})
const canSave = computed(
  () => Boolean(selected.value && pending.value && faceStatus.value === 'detected' && nextFreeSample.value),
)

let searchTimer: ReturnType<typeof setTimeout> | null = null
let pickerTimer: ReturnType<typeof setTimeout> | null = null

const showTableNotice = (type: 'success' | 'error', text: string) => {
  tableNotice.value = { type, text }
}

const showNotice = (type: 'success' | 'error', text: string) => {
  notice.value = { type, text }
}

/* ---------- Muat data ---------- */
const loadTable = async () => {
  tableLoading.value = true
  try {
    const res = await api.students({
      search: searchQuery.value.trim() || undefined,
      page: currentPage.value,
      per_page: perPage,
    })
    tableStudents.value = res.data
    totalData.value = res.meta.total
    totalPages.value = res.meta.last_page
  } catch (error) {
    showTableNotice('error', apiErrorMessage(error))
  } finally {
    tableLoading.value = false
  }
}

const loadPicker = async () => {
  pickerLoading.value = true
  try {
    const res = await api.students({ search: pickerSearch.value.trim() || undefined, per_page: 8 })
    pickerStudents.value = res.data
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    pickerLoading.value = false
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

watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    loadTable()
  }, 300)
})

watch(currentPage, loadTable)

watch(pickerSearch, () => {
  if (pickerTimer) clearTimeout(pickerTimer)
  pickerTimer = setTimeout(loadPicker, 300)
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
    showTableNotice('success', `Import selesai: ${parts.join(', ')}.`)
    justCreated.value = null
    currentPage.value = 1
    await Promise.all([loadTable(), loadPicker()])
  } catch (error) {
    showTableNotice('error', apiErrorMessage(error))
  } finally {
    importing.value = false
    input.value = ''
  }
}

/* ---------- Tambah / Edit / Hapus ---------- */
const isFormOpen = ref(false)
const savingStudent = ref(false)
const editingId = ref<number | null>(null)
const formError = ref('')
const form = reactive({
  nis: '',
  full_name: '',
  classroom_id: null as number | null,
  status: 'aktif',
})

const resetForm = () => {
  Object.assign(form, {
    nis: '',
    full_name: '',
    classroom_id: null,
    status: 'aktif',
  })
}

const openCreate = () => {
  editingId.value = null
  formError.value = ''
  resetForm()
  isFormOpen.value = true
}

const openEdit = (student: ApiStudent) => {
  editingId.value = student.id
  formError.value = ''
  Object.assign(form, {
    nis: student.nis ?? '',
    full_name: student.full_name ?? '',
    classroom_id: student.classroom?.id ?? null,
    status: student.status?.code ?? 'aktif',
  })
  isFormOpen.value = true
}

const submitStudent = async () => {
  if (!form.full_name.trim() || !form.nis.trim()) {
    formError.value = 'Nama lengkap dan NIS wajib diisi.'
    return
  }

  savingStudent.value = true
  formError.value = ''
  const payload = {
    nis: form.nis.trim(),
    full_name: form.full_name.trim(),
    classroom_id: form.classroom_id,
    status: form.status,
  }

  try {
    if (editingId.value) {
      const res = await api.updateStudent(editingId.value, payload)
      if (selected.value?.id === res.data.id) selected.value = res.data
      showTableNotice('success', `${res.data.full_name} berhasil diperbarui.`)
      justCreated.value = null
    } else {
      const res = await api.createStudent(payload)
      showTableNotice('success', `${res.data.full_name} berhasil ditambahkan.`)
      justCreated.value = res.data
    }

    isFormOpen.value = false
    currentPage.value = 1
    await Promise.all([loadTable(), loadPicker()])
  } catch (error) {
    formError.value = apiErrorMessage(error)
  } finally {
    savingStudent.value = false
  }
}

const removeStudent = async (student: ApiStudent) => {
  const ok = window.confirm(
    `Hapus ${student.full_name}? Sampel wajah dan riwayat absensinya juga akan terhapus. Tindakan ini tidak bisa dibatalkan.`,
  )
  if (!ok) return

  try {
    await api.deleteStudent(student.id)
    showTableNotice('success', `${student.full_name} dihapus.`)
    justCreated.value = null

    if (selected.value?.id === student.id) {
      stopFaceScan()
      selected.value = null
      samples.value = {}
      pending.value = null
    }

    await Promise.all([loadTable(), loadPicker()])
  } catch (error) {
    showTableNotice('error', apiErrorMessage(error))
  }
}

/* ---------- Registrasi wajah ---------- */
const applyFaceCount = (count: number) => {
  if (selected.value) {
    selected.value = { ...selected.value, face_descriptors_count: count }
  }
  pickerStudents.value = pickerStudents.value.map((s) =>
    s.id === selected.value?.id ? { ...s, face_descriptors_count: count } : s,
  )
  tableStudents.value = tableStudents.value.map((s) =>
    s.id === selected.value?.id ? { ...s, face_descriptors_count: count } : s,
  )
}

/** Pindah ke tab Registrasi Wajah lalu pilih siswa (dipakai dari tabel). */
const registerFaceFor = async (student: ApiStudent) => {
  justCreated.value = null
  activeTab.value = 'wajah'
  await selectStudent(student)

  if (!pickerStudents.value.some((s) => s.id === student.id)) {
    pickerStudents.value = [student, ...pickerStudents.value]
  }
}

const selectStudent = async (student: ApiStudent) => {
  selected.value = student
  samples.value = {}
  pending.value = null
  notice.value = null
  stopFaceScan()

  // Elemen <video> baru dirender setelah ada siswa terpilih, jadi kamera
  // diinisialisasi setelah DOM diperbarui — bukan saat mount.
  await ensureCamera()

  try {
    const res = await api.faceDescriptors(student.id)
    const map: Record<number, ApiFaceDescriptor> = {}
    for (const descriptor of res.data) map[descriptor.sample_no] = descriptor
    samples.value = map
    applyFaceCount(res.data.length)
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  }
}

/** Nyalakan kamera sekali saja; video sudah pasti ada saat dipanggil dari selectStudent. */
const ensureCamera = async () => {
  await nextTick()
  if (faceStatus.value === 'idle' || faceStatus.value === 'error') {
    initFace()
  }
}

// Wajah terdeteksi: simpan descriptor sebagai kandidat sampel.
watch(faceResult, (detected) => {
  if (!detected) return
  if (!selected.value) {
    stopFaceScan()
    return
  }
  pending.value = detected.descriptor
})

const startCapture = () => {
  notice.value = null
  pending.value = null

  // Kamera belum siap (atau gagal): coba nyalakan dulu.
  if (faceStatus.value === 'idle' || faceStatus.value === 'error') {
    initFace()
    return
  }
  if (faceStatus.value === 'loading') return

  startFaceScan()
}

const saveSample = async () => {
  if (!selected.value || !pending.value || !nextFreeSample.value) return

  saving.value = true
  const sampleNo = nextFreeSample.value
  try {
    const res = await api.registerFace(selected.value.id, pending.value, { sample_no: sampleNo })
    samples.value = { ...samples.value, [res.data.sample_no]: res.data }
    pending.value = null

    const total = Object.keys(samples.value).length
    applyFaceCount(total)
    showNotice('success', `Sampel ${res.data.sample_no} tersimpan (${total}/${maxSamples}).`)

    // Lanjut memindai sampel berikutnya bila belum lengkap.
    if (total < maxSamples) startFaceScan()
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    saving.value = false
  }
}

const removeSample = async (sampleNo: number) => {
  const descriptor = samples.value[sampleNo]
  if (!selected.value || !descriptor) return

  try {
    await api.deleteFace(selected.value.id, descriptor.id)
    const map = { ...samples.value }
    delete map[sampleNo]
    samples.value = map
    applyFaceCount(Object.keys(map).length)
    showNotice('success', `Sampel ${sampleNo} dihapus.`)
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  }
}

/* ---------- Lifecycle ---------- */
onMounted(async () => {
  const tabParam = route.query.tab
  if (tabParam === 'wajah' || tabParam === 'siswa') activeTab.value = tabParam

  await Promise.all([loadTable(), loadPicker(), loadClassrooms()])

  const preselect = Number(route.query.student)
  if (!preselect) return

  activeTab.value = 'wajah'
  const found =
    pickerStudents.value.find((s) => s.id === preselect) ??
    tableStudents.value.find((s) => s.id === preselect)

  if (found) {
    await selectStudent(found)
    return
  }

  try {
    const res = await api.student(preselect)
    await selectStudent(res.data)
  } catch {
    // Siswa tidak ditemukan — biarkan pengguna memilih manual.
  }
})

onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer)
  if (pickerTimer) clearTimeout(pickerTimer)
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
