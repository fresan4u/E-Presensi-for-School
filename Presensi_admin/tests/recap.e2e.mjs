/**
 * E2E: Rekap Absensi harian per kelas (app petugas, :3001).
 *
 * Test ini MEMBUAT DATANYA SENDIRI lewat API (3 siswa di kelas X RPL 1),
 * jadi tidak bergantung pada seeder roster. Perilaku tombol WhatsApp +
 * consent wali diuji di feature test backend (AttendanceRecapTest).
 *
 * Menguji:
 *   1. Filter cascade (Jurusan -> Kelas); Kelas terkunci sebelum Jurusan dipilih.
 *   2. Tabel rekap memuat seluruh siswa kelas tsb (jumlah = hasil API rekap).
 *   3. Status turunan "Alpa (belum absen)" untuk siswa tanpa baris absensi.
 *   4. Fallback "Kontak wali belum tersedia" saat wali tidak ber-consent.
 *   5. Export menghasilkan berkas Excel .xlsx asli.
 *
 * Prasyarat: backend + dev server petugas harus jalan.
 * Jalankan: node tests/recap.e2e.mjs
 */
import { chromium } from 'playwright'
import { mkdirSync, readFileSync } from 'node:fs'

const PETUGAS = process.env.PRESENSI_PETUGAS_WEB || 'http://localhost:3001'
const API = process.env.PRESENSI_API || 'http://127.0.0.1:8000/api/v1'
const PETUGAS_EMAIL = process.env.PRESENSI_PETUGAS_EMAIL || 'bagus.wijaya@sekolah.id'
const PASSWORD = process.env.PRESENSI_PASSWORD || 'password'
const SHOT_DIR = '/tmp/opencode/shots'
mkdirSync(SHOT_DIR, { recursive: true })

let failures = 0
const log = (name, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'} | ${name}${extra ? ` — ${extra}` : ''}`)
  if (!ok) failures++
}

/** Login lewat API untuk mendapat token Sanctum (semua endpoint terkunci). */
async function apiLogin(email, password = PASSWORD) {
  const res = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  const json = await res.json()
  return { token: json.data.token, user: json.data.user }
}

const token = (await apiLogin(PETUGAS_EMAIL)).token
const authHeaders = {
  'Content-Type': 'application/json',
  Accept: 'application/json',
  Authorization: `Bearer ${token}`,
}

const getJson = async (url) => (await fetch(url, { headers: authHeaders })).json()

const today = new Date().toISOString().slice(0, 10)

/* ---------- Siapkan data sendiri (tanpa seeder) ---------- */
const majors = (await getJson(`${API}/majors`)).data
const rpl = majors.find((m) => m.code === 'RPL')
const rooms = (await getJson(`${API}/classrooms`)).data
const kelas = rooms.find((r) => r.name === 'X RPL 1')
log('kelas "X RPL 1" tersedia dari API', Boolean(kelas), kelas?.name ?? '-')

const JUMLAH_UJI = 3
const suffix = Date.now().toString().slice(-7)
const created = []
for (let i = 0; i < JUMLAH_UJI; i++) {
  const res = await fetch(`${API}/students`, {
    method: 'POST',
    headers: authHeaders,
    body: JSON.stringify({
      nis: `R${suffix}${i}`,
      full_name: `Siswa Rekap ${suffix.slice(-4)}-${i + 1}`,
      classroom_id: kelas.id,
    }),
  })
  const json = await res.json()
  if (json.data?.id) created.push(json.data.id)
}
log(`membuat ${JUMLAH_UJI} siswa uji di kelas`, created.length === JUMLAH_UJI, `${created.length} dibuat`)

// Jumlah baris yang diharapkan = total pada API rekap (sumber yang sama dgn UI).
const expectedTotal = (await getJson(`${API}/attendances/recap?classroom_id=${kelas.id}&date=${today}`))
  .data.summary.total
log('API rekap mengembalikan roster kelas', expectedTotal >= JUMLAH_UJI, `${expectedTotal} siswa`)

/* ---------- Buka halaman rekap ---------- */
const browser = await chromium.launch()
const ctx = await browser.newContext({ acceptDownloads: true })

// Set cookie sesi seperti hasil login (useAuth membaca token + profil).
const authUser = { name: 'Petugas Demo', role: 'Petugas', email: PETUGAS_EMAIL }
await ctx.addCookies([
  { name: 'presensi-auth-token', value: token, url: PETUGAS },
  {
    name: 'presensi-auth-user',
    value: encodeURIComponent(JSON.stringify(authUser)),
    url: PETUGAS,
  },
])

const page = await ctx.newPage()
const errors = []
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
page.on('console', (m) => {
  if (m.type() === 'error') errors.push(`console: ${m.text()}`)
})

const waitForHydration = (target) =>
  target.waitForFunction(() => Boolean(document.querySelector('#__nuxt')?.__vue_app__), {
    timeout: 30000,
  })

await page.goto(`${PETUGAS}/petugas/rekap-harian`, {
  waitUntil: 'domcontentloaded',
  timeout: 60000,
})
await waitForHydration(page)

log('halaman rekap terbuka', page.url().includes('/petugas/rekap-harian'), page.url())
log('kartu filter tampil', await page.getByText('Filter Rekap').isVisible())
log(
  'empty state sebelum pilih kelas',
  await page.getByText('Pilih kelas untuk melihat rekap').isVisible(),
)

const kelasSelect = page.getByTestId('recap-classroom')
log('dropdown Kelas terkunci sebelum Jurusan dipilih', await kelasSelect.isDisabled())

await page.getByTestId('recap-major').selectOption(String(rpl.id))
log('dropdown Kelas aktif setelah Jurusan dipilih', await kelasSelect.isEnabled())

const recapResponse = page.waitForResponse(
  (r) => r.url().includes('/attendances/recap') && r.status() === 200,
  { timeout: 15000 },
)
await kelasSelect.selectOption(String(kelas.id))
await recapResponse

await page.waitForSelector('table tbody tr', { timeout: 15000 })
const rowCount = await page.locator('table tbody tr').count()
log('tabel rekap memuat seluruh roster kelas', rowCount === expectedTotal, `${rowCount} baris`)

log(
  'status alpa berlabel "Alpa (belum absen)"',
  await page.getByText('Alpa (belum absen)').first().isVisible(),
)

// Tanpa wali ber-consent, kolom aksi menampilkan fallback (bukan tombol palsu).
log(
  'fallback "Kontak wali belum tersedia" tampil',
  await page.getByText('Kontak wali belum tersedia').first().isVisible(),
)

// Export Excel
const [download] = await Promise.all([
  page.waitForEvent('download', { timeout: 20000 }),
  page.getByTestId('export-xlsx').click(),
])
const filename = download.suggestedFilename()
log('export menghasilkan berkas .xlsx', filename.endsWith('.xlsx'), filename)

const downloadPath = await download.path()
const magic = readFileSync(downloadPath).subarray(0, 2).toString('utf8')
log('berkas .xlsx valid (magic "PK")', magic === 'PK', magic)

await page.screenshot({ path: `${SHOT_DIR}/rekap-harian.png`, fullPage: true })
log('tanpa error JS', errors.length === 0, errors.slice(0, 3).join(' | '))

await browser.close()
console.log(failures === 0 ? '\nSEMUA TEST REKAP LULUS' : `\n${failures} TEST REKAP GAGAL`)
process.exit(failures === 0 ? 0 : 1)
