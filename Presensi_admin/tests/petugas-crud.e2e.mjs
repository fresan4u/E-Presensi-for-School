/**
 * E2E: Kelola Petugas (app admin, :3000).
 *
 * Menguji alur CRUD penuh lewat UI:
 *   1. Menu "Kelola Petugas" ada & halaman terbuka.
 *   2. Tambah petugas baru.
 *   3. Aktif/nonaktif akun.
 *   4. Ubah nama petugas.
 *   5. Hapus petugas.
 *
 * Test membuat datanya sendiri lalu membersihkannya lewat UI (hapus).
 * Prasyarat: backend + dev server admin harus jalan.
 * Jalankan: node tests/petugas-crud.e2e.mjs
 */
import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'

const ADMIN = process.env.PRESENSI_WEB || 'http://localhost:3000'
const API = process.env.PRESENSI_API || 'http://127.0.0.1:8000/api/v1'
const ADMIN_EMAIL = process.env.PRESENSI_ADMIN_EMAIL || 'andi.pratama@sekolah.id'
const PASSWORD = process.env.PRESENSI_PASSWORD || 'password'
const SHOT_DIR = '/tmp/opencode/shots'
mkdirSync(SHOT_DIR, { recursive: true })

let failures = 0
const log = (name, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'} | ${name}${extra ? ` — ${extra}` : ''}`)
  if (!ok) failures++
}

const stamp = Date.now().toString().slice(-7)
const nama = `Petugas E2E ${stamp}`
const namaBaru = `Petugas E2E ${stamp} Diubah`
const email = `e2e.petugas.${stamp}@sekolah.id`

// Login nyata lewat API agar punya token Sanctum yang valid.
const loginRes = await fetch(`${API}/auth/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email: ADMIN_EMAIL, password: PASSWORD }),
})
const { token, user } = (await loginRes.json()).data

const browser = await chromium.launch()
const ctx = await browser.newContext()

const authUser = { name: user.name, role: 'Admin', email: user.email }
await ctx.addCookies([
  { name: 'presensi-auth-token', value: token, url: ADMIN },
  {
    name: 'presensi-auth-user',
    value: encodeURIComponent(JSON.stringify(authUser)),
    url: ADMIN,
  },
])

const page = await ctx.newPage()
const errors = []
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
page.on('console', (m) => {
  if (m.type() === 'error') errors.push(`console: ${m.text()}`)
})
// Setuju pada window.confirm() saat menghapus.
page.on('dialog', (d) => d.accept())

const waitForHydration = (target) =>
  target.waitForFunction(() => Boolean(document.querySelector('#__nuxt')?.__vue_app__), {
    timeout: 30000,
  })

await page.goto(`${ADMIN}/`, { waitUntil: 'domcontentloaded', timeout: 60000 })
await waitForHydration(page)

// 1. Menu sidebar.
const menu = page.getByRole('link', { name: 'Kelola Petugas' })
log('menu "Kelola Petugas" tampil di sidebar', await menu.isVisible())

await menu.click()
await page.waitForURL('**/kelola-petugas', { timeout: 15000 })
await waitForHydration(page)
log('halaman kelola petugas terbuka', page.url().includes('/kelola-petugas'), page.url())

const rowPetugas = page.locator('table tbody tr', { hasText: email })

// 2. Tambah.
await page.getByTestId('tambah-petugas').click()
await page.getByPlaceholder('Nama petugas').fill(nama)
await page.getByPlaceholder('nama@sekolah.id').fill(email)
await page.getByPlaceholder('Minimal 6 karakter').fill('rahasia123')
await page.getByRole('button', { name: 'Simpan' }).click()

await page.getByText('Petugas baru berhasil ditambahkan.').waitFor({ timeout: 15000 })
log('petugas baru berhasil ditambahkan', await rowPetugas.count() === 1)

// 3. Aktif/nonaktif.
await page.locator(`button[aria-label="Nonaktifkan ${nama}"]`).click()
await page.getByText(`${nama} dinonaktifkan.`).waitFor({ timeout: 15000 })
await rowPetugas.getByText('Non Aktif').waitFor({ timeout: 15000 })
log('status berubah jadi Non Aktif', await rowPetugas.getByText('Non Aktif').isVisible())

// 4. Ubah nama.
await page.locator(`button[aria-label="Ubah ${nama}"]`).click()
await page.getByPlaceholder('Nama petugas').fill(namaBaru)
await page.getByRole('button', { name: 'Simpan' }).click()
await page.getByText('Data petugas berhasil diperbarui.').waitFor({ timeout: 15000 })
log('nama petugas diperbarui', await page.locator('table tbody tr', { hasText: namaBaru }).count() === 1)

// 5. Hapus.
await page.locator(`button[aria-label="Hapus ${namaBaru}"]`).click()
await page.getByText(`${namaBaru} dihapus.`).waitFor({ timeout: 15000 })
await page.waitForTimeout(500)
log('petugas dihapus', await page.locator('table tbody tr', { hasText: email }).count() === 0)

await page.screenshot({ path: `${SHOT_DIR}/kelola-petugas.png`, fullPage: true })
log('tanpa error JS', errors.length === 0, errors.slice(0, 3).join(' | '))

await browser.close()
console.log(failures === 0 ? '\nSEMUA TEST KELOLA PETUGAS LULUS' : `\n${failures} TEST KELOLA PETUGAS GAGAL`)
process.exit(failures === 0 ? 0 : 1)
