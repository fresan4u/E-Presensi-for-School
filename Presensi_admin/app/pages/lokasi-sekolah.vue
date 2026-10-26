<template>
  <div class="flex flex-col gap-6">
    <!-- Notifikasi hasil aksi -->
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
      <!-- Form pengaturan -->
      <base-card
        class="lg:col-span-5"
        title="Lokasi Sekolah"
        subtitle="Titik pusat area absensi mandiri siswa (geofencing)."
      >
        <form class="flex flex-col gap-4" @submit.prevent="save">
          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Latitude</span>
            <input
              v-model.number="form.latitude"
              type="number"
              step="any"
              inputmode="decimal"
              class="input font-mono"
              placeholder="-6.2000000"
            />
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Longitude</span>
            <input
              v-model.number="form.longitude"
              type="number"
              step="any"
              inputmode="decimal"
              class="input font-mono"
              placeholder="106.8166667"
            />
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-medium text-slate-800">Radius (meter)</span>
            <input
              v-model.number="form.radius_meter"
              type="number"
              min="10"
              max="5000"
              step="10"
              inputmode="numeric"
              class="input font-mono"
            />
            <span class="text-xs text-slate-400">
              Siswa bisa absen selama jarak HP ke titik ini masih di bawah radius.
              Mulai dari 100 m, naikkan bila sinyal GPS di sekolah kurang akurat.
            </span>
          </label>

          <div class="flex flex-wrap gap-3">
            <base-button type="button" variant="outline" :disabled="locating" @click="useCurrentLocation">
              <LocateFixed class="h-4 w-4" :class="locating ? 'animate-pulse' : ''" />
              {{ locating ? 'Mengambil GPS…' : 'Gunakan lokasi saya' }}
            </base-button>
            <base-button type="submit" :loading="saving">
              <Save class="h-4 w-4" />
              Simpan
            </base-button>
          </div>

          <p v-if="lastUpdated" class="text-xs text-slate-400">
            Terakhir disimpan: {{ lastUpdated }}
          </p>
        </form>
      </base-card>

      <!-- Pratinjau peta -->
      <base-card
        class="lg:col-span-7"
        title="Pratinjau Peta"
        subtitle="Titik merah adalah pusat area absensi. Lingkaran radius tidak ditampilkan oleh OpenStreetMap."
      >
        <div class="overflow-hidden rounded-md border border-gray-200">
          <iframe
            v-if="previewUrl"
            :src="previewUrl"
            title="Pratinjau lokasi sekolah"
            class="h-80 w-full"
            loading="lazy"
          />
          <div
            v-else
            class="flex h-80 items-center justify-center bg-slate-50 px-6 text-center text-sm text-gray-500"
          >
            Isi latitude & longitude yang valid untuk melihat pratinjau peta.
          </div>
        </div>

        <a
          v-if="previewUrl"
          :href="mapLink"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-800 hover:underline"
        >
          <MapPin class="h-4 w-4" />
          Buka di OpenStreetMap
        </a>
      </base-card>
    </div>

    <!-- Panduan pengambilan koordinat -->
    <base-card title="Cara Mengambil Koordinat yang Benar" subtitle="Ikuti salah satu cara di bawah.">
      <ol class="flex list-decimal flex-col gap-3 pl-5 text-sm text-gray-600">
        <li>
          <span class="font-medium text-slate-800">Lewat Google Maps (paling akurat):</span>
          buka Google Maps, cari nama sekolah, lalu klik kanan tepat di titik gerbang/pusat sekolah.
          Klik koordinat yang muncul untuk menyalinnya. Tempel angka pertama ke
          <span class="font-medium">Latitude</span> dan angka kedua ke
          <span class="font-medium">Longitude</span>.
        </li>
        <li>
          <span class="font-medium text-slate-800">Lewat HP di lokasi sekolah:</span>
          berdiri di area sekolah, buka halaman ini dari HP, lalu tekan
          <span class="font-medium">Gunakan lokasi saya</span>. Koordinat GPS akan terisi otomatis.
        </li>
        <li>
          <span class="font-medium text-slate-800">Atur radius:</span>
          ukur kira-kira jarak dari titik pusat ke batas area yang masih boleh absen.
          Radius 100 m cocok untuk satu gedung; 200–300 m untuk kampus yang luas.
        </li>
      </ol>

      <div class="mt-4 flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <Info class="mt-0.5 h-4 w-4 shrink-0" />
        <p>
          Pastikan titik yang disimpan adalah koordinat <span class="font-medium">sekolah</span>,
          bukan lokasi rumah. Aplikasi siswa membaca titik ini setiap kali scan, jadi
          perubahan langsung berlaku tanpa perlu deploy ulang.
        </p>
      </div>
    </base-card>
  </div>
</template>

<script setup lang="ts">
import { CheckCircle2, Info, LocateFixed, MapPin, Save, TriangleAlert } from '@lucide/vue'

definePageMeta({
  layout: 'default',
  title: 'Lokasi Sekolah',
})

const api = useApi()

const loading = ref(false)
const saving = ref(false)
const locating = ref(false)
const lastUpdated = ref<string | null>(null)
const notice = ref<{ type: 'success' | 'error'; text: string } | null>(null)

const form = reactive({
  latitude: -6.18841,
  longitude: 106.924823,
  radius_meter: 100,
})

const showNotice = (type: 'success' | 'error', text: string) => {
  notice.value = { type, text }
}

const isValidCoordinate = computed(
  () =>
    Number.isFinite(form.latitude)
    && Number.isFinite(form.longitude)
    && form.latitude >= -90
    && form.latitude <= 90
    && form.longitude >= -180
    && form.longitude <= 180,
)

const formatCoordinate = (value: number) => value.toFixed(6)

/** Bounding box kecil di sekitar titik (untuk iframe OpenStreetMap). */
const previewUrl = computed(() => {
  if (!isValidCoordinate.value) return null

  const lat = form.latitude
  const lng = form.longitude
  const delta = 0.004
  const bbox = [
    formatCoordinate(lng - delta),
    formatCoordinate(lat - delta),
    formatCoordinate(lng + delta),
    formatCoordinate(lat + delta),
  ].join(',')

  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${formatCoordinate(lat)},${formatCoordinate(lng)}`
})

const mapLink = computed(
  () => `https://www.openstreetmap.org/?mlat=${form.latitude}&mlon=${form.longitude}#map=18/${form.latitude}/${form.longitude}`,
)

const loadSettings = async () => {
  loading.value = true
  try {
    const res = await api.schoolLocation()
    form.latitude = res.data.latitude
    form.longitude = res.data.longitude
    form.radius_meter = res.data.radius_meter
    lastUpdated.value = res.data.updated_at
      ? new Date(res.data.updated_at).toLocaleString('id-ID')
      : null
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    loading.value = false
  }
}

const useCurrentLocation = () => {
  if (!navigator.geolocation) {
    showNotice('error', 'Perangkat/browser ini belum mendukung GPS.')
    return
  }

  locating.value = true
  navigator.geolocation.getCurrentPosition(
    (position) => {
      form.latitude = Number(position.coords.latitude.toFixed(6))
      form.longitude = Number(position.coords.longitude.toFixed(6))
      locating.value = false
      showNotice('success', 'Koordinat GPS berhasil diambil. Jangan lupa tekan Simpan.')
    },
    () => {
      locating.value = false
      showNotice('error', 'Gagal mengambil lokasi. Izinkan akses GPS/lokasi lalu coba lagi.')
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  )
}

const save = async () => {
  if (!isValidCoordinate.value) {
    showNotice('error', 'Latitude/longitude tidak valid.')
    return
  }

  if (form.radius_meter < 10 || form.radius_meter > 5000) {
    showNotice('error', 'Radius harus antara 10 dan 5000 meter.')
    return
  }

  saving.value = true
  try {
    const res = await api.updateSchoolLocation({
      latitude: form.latitude,
      longitude: form.longitude,
      radius_meter: form.radius_meter,
    })
    lastUpdated.value = res.data.updated_at
      ? new Date(res.data.updated_at).toLocaleString('id-ID')
      : null
    showNotice('success', res.message || 'Lokasi sekolah berhasil disimpan.')
  } catch (error) {
    showNotice('error', apiErrorMessage(error))
  } finally {
    saving.value = false
  }
}

onMounted(loadSettings)
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
