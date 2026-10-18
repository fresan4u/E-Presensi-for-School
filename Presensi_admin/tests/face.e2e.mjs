/**
 * E2E integrasi pengenalan wajah (butuh backend + dev server jalan):
 *   1. Registrasi 3 sampel wajah lewat UI /registrasi-wajah (Presensi_admin, :3000)
 *   2. Absen di kiosk publik /scan (Presensi_petugas, :3001)
 *   3. Absen di mesin operasional petugas /petugas/mesin-absen (Presensi_petugas, :3001)
 *
 * Memakai kamera palsu Chromium yang diumpan foto fixture (tests/fixtures/face.jpg).
 * Berkas video Y4M dibuat otomatis saat pertama dijalankan.
 *
 * Prasyarat:
 *   - backend Laravel aktif di $PRESENSI_API (default http://127.0.0.1:8000/api/v1)
 *   - dev server admin Nuxt aktif di $PRESENSI_WEB (default http://localhost:3000)
 *   - dev server petugas Nuxt aktif di $PRESENSI_PETUGAS_WEB (default http://localhost:3001)
 *
 * Jalankan:  node tests/face.e2e.mjs
 *
 * Catatan: test ini MENGHAPUS seluruh descriptor wajah yang ada lalu
 * mendaftar ulang satu siswa, dan mencatat absensi hari ini.
 */
import { chromium } from 'playwright'
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'
import { tmpdir } from 'node:os'

const here = dirname(fileURLToPath(import.meta.url))
const FIXTURE_JPG = join(here, 'fixtures', 'face.jpg')
const Y4M = process.env.PRESENSI_FAKE_VIDEO || join(tmpdir(), 'presensi-face.y4m')
const BASE = process.env.PRESENSI_WEB || 'http://localhost:3000'
const PETUGAS = process.env.PRESENSI_PETUGAS_WEB || 'http://localhost:3001'
const API = process.env.PRESENSI_API || 'http://127.0.0.1:8000/api/v1'
const ADMIN_EMAIL = process.env.PRESENSI_ADMIN_EMAIL || 'andi.pratama@sekolah.id'
const PETUGAS_EMAIL = process.env.PRESENSI_PETUGAS_EMAIL || 'bagus.wijaya@sekolah.id'
const PASSWORD = process.env.PRESENSI_PASSWORD || 'password'

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

const authHeaders = (token) => ({
  'Content-Type': 'application/json',
  Accept: 'application/json',
  Authorization: `Bearer ${token}`,
})

/** Pastikan ada minimal satu siswa (dibuat lewat API) supaya UI registrasi terisi. */
async function ensureStudent(token) {
  const headers = authHeaders(token)
  const list = await (await fetch(`${API}/students?per_page=1`, { headers })).json()
  if (list.data[0]) return list.data[0]

  const classrooms = await (await fetch(`${API}/classrooms`, { headers })).json()
  const created = await (await fetch(`${API}/students`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      nis: `E2E${Date.now()}`.slice(0, 20),
      full_name: 'Siswa Uji Wajah',
      classroom_id: classrooms.data[0]?.id ?? null,
    }),
  })).json()

  return created.data
}

/* ---------- Fixture kamera: buat Y4M dari JPEG bila belum ada ---------- */
async function ensureFakeVideo() {
  if (existsSync(Y4M)) return
  if (!existsSync(FIXTURE_JPG)) {
    throw new Error(`Fixture wajah tidak ditemukan: ${FIXTURE_JPG}`)
  }

  const W = 640
  const H = 480
  const b64 = readFileSync(FIXTURE_JPG).toString('base64')

  const browser = await chromium.launch()
  const page = await browser.newPage()
  const planes = await page.evaluate(async ({ b64, W, H }) => {
    const img = new Image()
    img.src = `data:image/jpeg;base64,${b64}`
    await img.decode()

    const canvas = document.createElement('canvas')
    canvas.width = W
    canvas.height = H
    const ctx = canvas.getContext('2d')
    // Crop area wajah supaya pengenalan lebih stabil.
    const sx = img.width * 0.22
    const sy = img.height * 0.02
    const sw = img.width * 0.56
    const sh = img.height * 0.60
    const scale = Math.max(W / sw, H / sh)
    const dw = sw * scale
    const dh = sh * scale
    ctx.fillStyle = '#000'
    ctx.fillRect(0, 0, W, H)
    ctx.drawImage(img, sx, sy, sw, sh, (W - dw) / 2, (H - dh) / 2, dw, dh)

    const data = ctx.getImageData(0, 0, W, H).data
    const clamp = (v) => (v < 0 ? 0 : v > 255 ? 255 : v)
    const Y = new Uint8Array(W * H)
    const U = new Uint8Array((W / 2) * (H / 2))
    const V = new Uint8Array((W / 2) * (H / 2))

    for (let y = 0; y < H; y++) {
      for (let x = 0; x < W; x++) {
        const i = (y * W + x) * 4
        Y[y * W + x] = clamp(0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2])
      }
    }
    for (let y = 0; y < H; y += 2) {
      for (let x = 0; x < W; x += 2) {
        let rs = 0, gs = 0, bs = 0
        for (let dy = 0; dy < 2; dy++) {
          for (let dx = 0; dx < 2; dx++) {
            const i = ((y + dy) * W + (x + dx)) * 4
            rs += data[i]; gs += data[i + 1]; bs += data[i + 2]
          }
        }
        rs /= 4; gs /= 4; bs /= 4
        const idx = (y / 2) * (W / 2) + (x / 2)
        U[idx] = clamp(-0.169 * rs - 0.331 * gs + 0.5 * bs + 128)
        V[idx] = clamp(0.5 * rs - 0.419 * gs - 0.081 * bs + 128)
      }
    }
    return { Y: Array.from(Y), U: Array.from(U), V: Array.from(V) }
  }, { b64, W, H })
  await browser.close()

  const header = `YUV4MPEG2 W${W} H${H} F30:1 Ip A1:1 C420mpeg2\n`
  const frame = Buffer.concat([Buffer.from(planes.Y), Buffer.from(planes.U), Buffer.from(planes.V)])
  const parts = [Buffer.from(header)]
  for (let i = 0; i < 60; i++) parts.push(Buffer.from('FRAME\n'), frame)
  writeFileSync(Y4M, Buffer.concat(parts))
  console.log('Fixture video dibuat:', Y4M)
}

/* ---------- Helper ---------- */
const launchArgs = [
  '--use-fake-ui-for-media-stream',
  '--use-fake-device-for-media-stream',
  `--use-file-for-fake-video-capture=${Y4M}`,
]

async function loginAsAdmin(page) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' })
  await waitForHydration(page)
  await page.getByLabel('Email / Username', { exact: true }).fill(ADMIN_EMAIL)
  await page.getByLabel('Password', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Masuk' }).click()
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 15000 })
}

/** Login di aplikasi petugas (mesin absensi kini milik panel petugas). */
async function loginAsPetugas(page) {
  await page.goto(`${PETUGAS}/login`, { waitUntil: 'networkidle' })
  await waitForHydration(page)
  await page.getByLabel('Email / Username', { exact: true }).fill(PETUGAS_EMAIL)
  await page.getByLabel('Password', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Masuk' }).click()
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 15000 })
}

/**
 * Tunggu Vue selesai hydrate. Navigasi berhenti di `domcontentloaded` (HTML
 * SSR sudah tampil) jauh sebelum handler klik terpasang; tanpa ini klik bisa
 * "hilang" dan UI tampak tidak merespons.
 */
const waitForHydration = (page) =>
  page.waitForFunction(() => Boolean(document.querySelector('#__nuxt')?.__vue_app__), {
    timeout: 30000,
  })

const videoReady = () =>
  document.querySelector('video')?.srcObject && document.querySelector('video').readyState >= 2

/* ---------- Jalankan ---------- */
await ensureFakeVideo()

const adminToken = await apiLogin(ADMIN_EMAIL)

// Bersihkan descriptor lama supaya test idempoten.
const list = await (await fetch(`${API}/students?per_page=200`, { headers: authHeaders(adminToken) })).json()
for (const s of list.data) {
  if ((s.face_descriptors_count ?? 0) > 0) {
    const res = await (await fetch(`${API}/students/${s.id}/face-descriptors`, { headers: authHeaders(adminToken) })).json()
    for (const d of res.data) {
      await fetch(`${API}/students/${s.id}/face-descriptors/${d.id}`, {
        method: 'DELETE',
        headers: authHeaders(adminToken),
      })
    }
  }
}

await ensureStudent(adminToken)

const browser = await chromium.launch({ headless: true, args: launchArgs })

/* 1. Registrasi wajah */
const ctx = await browser.newContext({ permissions: ['camera'] })
const page = await ctx.newPage()
const errors = []
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
page.on('console', (m) => { if (m.type() === 'error') errors.push(`console: ${m.text()}`) })

await loginAsAdmin(page)
await page.goto(`${BASE}/registrasi-wajah`, { waitUntil: 'domcontentloaded', timeout: 60000 })
// Halaman kini bertab (default "Data Siswa") — buka tab "Registrasi Wajah".
await page.waitForSelector('[data-testid="tab-registrasi-wajah"]', { timeout: 20000 })
await waitForHydration(page)
await page.locator('[data-testid="tab-registrasi-wajah"]').click()
await page.waitForSelector('button:has-text("/3")', { timeout: 20000 })

const studentButtons = page.locator('button:has-text("/3")')
log('daftar siswa termuat dari API', (await studentButtons.count()) > 0, `${await studentButtons.count()} siswa`)

const firstText = await studentButtons.first().innerText()
const chosenName = (firstText.split('\n').filter(Boolean)[1] || '').trim()
await studentButtons.first().click()
console.log('Siswa dipilih:', chosenName)

await page.waitForSelector('button:has-text("Mulai Pemindaian")', { timeout: 30000 })
await page.locator('button:has-text("Mulai Pemindaian")').click()

const saveBtn = page.locator('[data-testid="simpan-sampel"]')
for (let n = 1; n <= 3; n++) {
  try {
    await page.waitForFunction(
      () => {
        const b = document.querySelector('[data-testid="simpan-sampel"]')
        return b && !b.hasAttribute('disabled')
      },
      { timeout: 30000 },
    )
  } catch {
    log(`sampel ${n} terdeteksi`, false)
    break
  }
  log(`sampel ${n} terdeteksi`, true)
  await saveBtn.click()
  try {
    await page.waitForFunction((num) => (document.body.innerText || '').includes(`Sampel ${num} tersimpan`), n, { timeout: 15000 })
    log(`sampel ${n} tersimpan`, true)
  } catch {
    log(`sampel ${n} tersimpan`, false)
    break
  }
}

const regText = await page.evaluate(() => document.body.innerText || '')
log('registrasi 3 sampel lengkap', regText.includes('Registrasi wajah lengkap'))
await ctx.close()

const after = await (await fetch(`${API}/students?per_page=200`, { headers: authHeaders(adminToken) })).json()
const registered = after.data.find((s) => (s.face_descriptors_count ?? 0) >= 3)
log('backend menyimpan 3 descriptor', Boolean(registered), registered ? `${registered.full_name} (${registered.face_descriptors_count})` : 'tidak ada')

/* 2. Kiosk /scan */
const ctx2 = await browser.newContext({ permissions: ['camera'] })
const page2 = await ctx2.newPage()
const errors2 = []
page2.on('pageerror', (e) => errors2.push(`pageerror: ${e.message}`))
page2.on('console', (m) => { if (m.type() === 'error') errors2.push(`console: ${m.text()}`) })

await page2.goto(`${PETUGAS}/scan`, { waitUntil: 'domcontentloaded', timeout: 60000 })
await page2.waitForSelector('[data-testid="mulai-scan"]', { timeout: 30000 })
await waitForHydration(page2)
await page2.waitForFunction(videoReady, { timeout: 30000 }).catch(() => {})
await page2.locator('[data-testid="mulai-scan"]').click()

try {
  await page2.waitForFunction(() => (document.body.innerText || '').includes('Absensi tercatat'), { timeout: 45000 })
  log('kiosk /scan: wajah dikenali & absensi tercatat', true)
} catch {
  log('kiosk /scan: wajah dikenali & absensi tercatat', false)
}
const scanText = await page2.evaluate(() => document.body.innerText || '')
log('kiosk menampilkan nama siswa', scanText.includes(chosenName))
await ctx2.close()

/* 2b. Mesin operasional petugas (auto-scan) */
const ctx2b = await browser.newContext({ permissions: ['camera'] })
const page2b = await ctx2b.newPage()
await loginAsPetugas(page2b)
await page2b.goto(`${PETUGAS}/petugas/mesin-absen`, { waitUntil: 'domcontentloaded', timeout: 60000 })
await page2b.waitForFunction(videoReady, { timeout: 30000 }).catch(() => {})
try {
  await page2b.waitForFunction((name) => (document.body.innerText || '').includes(name), chosenName, { timeout: 45000 })
  log('mesin-operasional: wajah dikenali & menampilkan siswa', true)
} catch {
  log('mesin-operasional: wajah dikenali & menampilkan siswa', false)
}
await ctx2b.close()

await browser.close()
log('tanpa error JS di halaman registrasi', errors.length === 0, errors.slice(0, 2).join(' | '))
console.log(failures === 0 ? '\nSEMUA TEST WAJAH LULUS' : `\n${failures} TEST GAGAL`)
process.exit(failures === 0 ? 0 : 1)
