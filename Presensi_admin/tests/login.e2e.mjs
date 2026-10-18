/**
 * E2E test: 1 gateway login admin -> 2 aplikasi terpisah.
 *   - Akun admin   -> dashboard admin (Presensi_admin, :3000)
 *   - Akun petugas -> dialihkan ke aplikasi petugas (Presensi_petugas, :3001)
 *
 * Role TIDAK dipilih di UI lagi: server yang menentukannya dari akun.
 * Petugas memakai layout responsif (sidebar di desktop, bottom-nav di mobile)
 * dan punya konfirmasi keluar dari mode kiosk (mesin absensi).
 *
 * Jalankan: node tests/login.e2e.mjs  (backend + admin + petugas harus jalan)
 */
import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'

const BASE = process.env.PRESENSI_WEB || 'http://localhost:3000'
const PETUGAS = process.env.PRESENSI_PETUGAS_WEB || 'http://localhost:3001'
const API = process.env.PRESENSI_API || 'http://127.0.0.1:8000/api/v1'
const ADMIN_EMAIL = process.env.PRESENSI_ADMIN_EMAIL || 'andi.pratama@sekolah.id'
const PETUGAS_EMAIL = process.env.PRESENSI_PETUGAS_EMAIL || 'bagus.wijaya@sekolah.id'
const PASSWORD = process.env.PRESENSI_PASSWORD || 'password'
const SHOT_DIR = '/tmp/opencode/shots'
mkdirSync(SHOT_DIR, { recursive: true })

let failures = 0
const log = (name, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'} | ${name}${extra ? ` — ${extra}` : ''}`)
  if (!ok) failures++
}

/** Login lewat API untuk mendapat token Sanctum (dipakai helper data). */
async function apiLogin(email, password = PASSWORD) {
  const res = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  return (await res.json()).data.token
}

/** Siapkan data lewat API: minimal 1 siswa + 1 absensi supaya tabel terisi. */
async function ensureStudentAndAttendance(token) {
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
  }

  const list = await (await fetch(`${API}/students?per_page=1`, { headers })).json()
  let student = list.data[0]

  if (!student) {
    const classrooms = await (await fetch(`${API}/classrooms`, { headers })).json()
    student = (await (await fetch(`${API}/students`, {
      method: 'POST',
      headers,
      body: JSON.stringify({
        nis: `E2E${Date.now()}`.slice(0, 20),
        full_name: 'Siswa Uji Login',
        classroom_id: classrooms.data[0]?.id ?? null,
      }),
    })).json()).data
  }

  const attendances = await (await fetch(`${API}/attendances?per_page=1`, { headers })).json()
  if (!attendances.data[0]) {
    await fetch(`${API}/attendances`, {
      method: 'POST',
      headers,
      body: JSON.stringify({
        student_id: student.id,
        session: 'masuk',
        status: 'hadir',
        method: 'manual',
      }),
    })
  }
}

await ensureStudentAndAttendance(await apiLogin(ADMIN_EMAIL))

const browser = await chromium.launch()

/**
 * Tunggu Vue selesai hydrate. Tanpa ini, klik bisa "hilang" karena handler
 * belum terpasang saat HTML SSR baru tampil.
 */
const waitForHydration = (page) =>
  page.waitForFunction(() => Boolean(document.querySelector('#__nuxt')?.__vue_app__), {
    timeout: 30000,
  })

/**
 * Isi form login di app admin. Role TIDAK dipilih lagi di UI — server yang
 * menentukan dari akun, jadi cukup email + password.
 */
async function submitAdminLogin(page, { email }) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' })
  await waitForHydration(page)
  log('[Admin] pemilih role sudah tidak ada', (await page.locator('fieldset button').count()) === 0)
  await page.getByLabel('Email / Username', { exact: true }).fill(email)
  await page.getByLabel('Password', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Masuk' }).click()
}

/* ---------- Skenario A: Akun Admin -> dashboard admin ---------- */
{
  const ctx = await browser.newContext()
  const page = await ctx.newPage()
  const errors = []
  page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
  page.on('console', (m) => { if (m.type() === 'error') errors.push(`console: ${m.text()}`) })

  // Tanpa sesi -> wajib redirect ke login.
  await page.goto(`${BASE}/`, { waitUntil: 'networkidle' })
  log('[Admin] redirect ke /login', page.url().includes('/login'), page.url())

  await submitAdminLogin(page, { email: ADMIN_EMAIL })
  try {
    await page.waitForURL((u) => u.origin === BASE && !u.pathname.includes('/login'), { timeout: 15000 })
    log('[Admin] masuk ke dashboard admin', page.url().replace(BASE, '') === '/', page.url())
  } catch {
    log('[Admin] masuk ke dashboard admin', false, `masih di ${page.url()}`)
  }

  const aside = page.locator('aside').first()
  const hasAside = await aside.isVisible().catch(() => false)
  if (hasAside) {
    const cls = (await aside.getAttribute('class')) || ''
    log('[Admin] sidebar hijau tampil', cls.includes('bg-emerald-900'))
    await page.getByRole('link', { name: 'Data Siswa' }).click()
    await page.waitForURL((u) => u.pathname === '/siswa', { timeout: 10000 })
    await page
      .waitForResponse((r) => r.url().includes('/api/v1/students') && r.status() === 200, { timeout: 10000 })
      .catch(() => {})
    await page.waitForSelector('table tbody tr', { timeout: 10000 }).catch(() => {})
    const rows = await page.locator('table tbody tr').count()
    log('[Admin] Data Siswa memuat baris dari API', rows > 0, `${rows} baris`)
  } else {
    log('[Admin] sidebar tampil', false, 'aside tidak ditemukan')
  }

  await page.screenshot({ path: `${SHOT_DIR}/after-login-admin.png` })
  log('[Admin] tanpa error JS', errors.length === 0, errors.slice(0, 3).join(' | '))
  await ctx.close()
}

/* ---------- Skenario B: Akun Petugas -> dialihkan ke app petugas ---------- */
{
  const ctx = await browser.newContext()
  const page = await ctx.newPage()
  const errors = []
  page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
  page.on('console', (m) => { if (m.type() === 'error') errors.push(`console: ${m.text()}`) })

  await submitAdminLogin(page, { email: PETUGAS_EMAIL })

  try {
    await page.waitForURL((u) => u.port === '3001', { timeout: 15000 })
    log('[Petugas] dialihkan ke aplikasi petugas (:3001)', page.url().startsWith(PETUGAS) && page.url().includes('/petugas'), page.url())
  } catch {
    log('[Petugas] dialihkan ke aplikasi petugas (:3001)', false, `masih di ${page.url()}`)
  }

  // Layout desktop: sidebar tampil dengan label "Panel Petugas".
  await page.waitForSelector('aside', { timeout: 10000 }).catch(() => {})
  const aside = page.locator('aside').first()
  const asideVisible = await aside.isVisible().catch(() => false)
  const asideText = await aside.innerText().catch(() => '')
  log('[Petugas] sidebar tampil (desktop)', asideVisible)
  log('[Petugas] sidebar "Panel Petugas"', asideText.includes('Panel Petugas'), asideText.replace(/\n/g, ' ').slice(0, 90))

  // Navigasi lewat sidebar ke Data Absensi.
  await page.getByRole('link', { name: 'Data Absensi' }).first().click()
  await page.waitForURL((u) => u.pathname === '/petugas/data-absensi', { timeout: 10000 }).catch(() => {})
  log('[Petugas] navigasi sidebar ke /petugas/data-absensi', page.url().includes('/petugas/data-absensi'), page.url())
  await page.waitForSelector('table tbody tr', { timeout: 10000 }).catch(() => {})
  const rows = await page.locator('table tbody tr').count()
  log('[Petugas] Data Absensi memuat baris dari API', rows > 0, `${rows} baris`)

  // Layout mobile: sidebar tersembunyi, bottom-nav tampil.
  await page.setViewportSize({ width: 390, height: 844 })
  await page.waitForTimeout(300)
  const asideVisibleMobile = await aside.isVisible().catch(() => false)
  const bottomNavVisible = await page
    .locator('nav')
    .last()
    .locator('a', { hasText: 'Absensi' })
    .first()
    .isVisible()
    .catch(() => false)
  log('[Petugas] mobile: sidebar tersembunyi', !asideVisibleMobile)
  log('[Petugas] mobile: bottom-nav tampil', bottomNavVisible)

  await page.screenshot({ path: `${SHOT_DIR}/after-login-petugas.png` })
  log('[Petugas] tanpa error JS', errors.length === 0, errors.slice(0, 3).join(' | '))
  await ctx.close()
}

/* ---------- Skenario C: Guard keluar mode kiosk (mesin absensi) ---------- */
{
  const ctx = await browser.newContext()
  const page = await ctx.newPage()

  await submitAdminLogin(page, { email: PETUGAS_EMAIL })
  await page.waitForURL((u) => u.port === '3001', { timeout: 15000 }).catch(() => {})

  await page.goto(`${PETUGAS}/petugas/mesin-absen`, { waitUntil: 'domcontentloaded', timeout: 60000 })
  await waitForHydration(page)
  const exitBtn = page.locator('header button', { hasText: 'Keluar' }).first()
  await exitBtn.waitFor({ timeout: 15000 }).catch(() => {})

  // Klik Keluar -> dialog konfirmasi muncul.
  await exitBtn.click()
  const dialog = page.locator('[role="dialog"]')
  const dialogShown = await dialog
    .waitFor({ state: 'visible', timeout: 8000 })
    .then(() => true)
    .catch(() => false)
  log('[Kiosk] dialog konfirmasi tampil', dialogShown)

  // Batal -> tetap di halaman.
  await dialog.getByRole('button', { name: 'Batal' }).click().catch(() => {})
  await page.waitForTimeout(400)
  log('[Kiosk] "Batal" tetap di mesin absensi', page.url().includes('mesin-absen'), page.url())

  // Keluar lagi -> konfirmasi -> pindah ke Beranda.
  await exitBtn.click()
  await dialog.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {})
  await dialog.getByRole('button', { name: 'Keluar' }).click().catch(() => {})
  const left = await page
    .waitForURL((u) => u.pathname === '/petugas', { timeout: 10000 })
    .then(() => true)
    .catch(() => false)
  log('[Kiosk] konfirmasi "Keluar" pindah ke Beranda', left, page.url())

  await ctx.close()
}

await browser.close()
console.log(failures === 0 ? '\nSEMUA TEST LULUS' : `\n${failures} TEST GAGAL`)
process.exit(failures === 0 ? 0 : 1)
