/**
 * E2E portal siswa (Presensi_mobile, :3002) — login NIS + PIN, wajib ganti PIN,
 * absensi wajah 1:1, riwayat, dan keluar.
 *
 * Prasyarat:
 *   - backend Laravel aktif di $PRESENSI_API (default http://127.0.0.1:8000/api/v1)
 *   - dev server mobile Nuxt aktif di $PRESENSI_MOBILE_WEB (default http://localhost:3002)
 *
 * Jalankan:  cd Presensi_mobile && node tests/mobile.e2e.mjs
 *
 * Catatan: test ini MEMBUAT siswa uji baru lewat API (NIS unik) dan, saat scan
 * pertama, mendaftarkan descriptor wajah yang tertangkap kamera palsu agar
 * pemindaian kedua berhasil. Aman diulang.
 */
import { chromium } from 'playwright'
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'
import { tmpdir } from 'node:os'

const here = dirname(fileURLToPath(import.meta.url))
const FIXTURE_JPG = join(here, 'fixtures', 'face.jpg')
const Y4M = process.env.PRESENSI_FAKE_VIDEO || join(tmpdir(), 'presensi-face.y4m')
const MOBILE = process.env.PRESENSI_MOBILE_WEB || 'http://localhost:3002'
const API = process.env.PRESENSI_API || 'http://127.0.0.1:8000/api/v1'
const ADMIN_EMAIL = process.env.PRESENSI_ADMIN_EMAIL || 'andi.pratama@sekolah.id'
const PASSWORD = process.env.PRESENSI_PASSWORD || 'password'

let failures = 0
const log = (name, ok, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'} | ${name}${extra ? ` — ${extra}` : ''}`)
  if (!ok) failures++
}

/* ---------- Helper API ---------- */

/** Login admin lewat API (semua endpoint operasional kini butuh token). */
async function apiLogin(email, password = PASSWORD) {
  const res = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  return (await res.json()).data.token
}

const adminToken = await apiLogin(ADMIN_EMAIL)

async function api(path, options = {}) {
  const res = await fetch(`${API}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${adminToken}`,
      ...(options.headers || {}),
    },
  })
  return { status: res.status, body: await res.json().catch(() => null) }
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

const launchArgs = [
  '--use-fake-ui-for-media-stream',
  '--use-fake-device-for-media-stream',
  `--use-file-for-fake-video-capture=${Y4M}`,
]

/** Tunggu Vue selesai hydrate sebelum mengklik. */
const waitForHydration = (page) =>
  page.waitForFunction(() => Boolean(document.querySelector('#__nuxt')?.__vue_app__), {
    timeout: 30000,
  })

const videoReady = () =>
  document.querySelector('video')?.srcObject && document.querySelector('video').readyState >= 2

const bodyText = (page) => page.evaluate(() => document.body.innerText || '')

/* ---------- Jalankan ---------- */
await ensureFakeVideo()

const nis = `E2E${Date.now()}`.slice(0, 20)
const pinBaru = '4321'
const nama = 'Siswa Uji Mobile'

const created = await api('/students', {
  method: 'POST',
  body: JSON.stringify({ nis, full_name: nama }),
})
const studentId = created.body?.data?.id
log('siswa uji dibuat lewat API', created.status === 201 && Boolean(studentId), `NIS ${nis}`)
if (!studentId) {
  console.log('Tidak bisa melanjutkan tanpa siswa uji.')
  process.exit(1)
}

const browser = await chromium.launch({ headless: true, args: launchArgs })
const ctx = await browser.newContext({ permissions: ['camera'] })
const page = await ctx.newPage()

const errors = []
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
page.on('console', (m) => {
  if (m.type() !== 'error') return
  const text = m.text()
  // 401/404 yang memang diuji bukan indikasi bug JS.
  if (text.includes('Failed to load resource')) return
  errors.push(`console: ${text}`)
})

/* 1. Tanpa sesi -> diarahkan ke login */
await page.goto(`${MOBILE}/siswa`, { waitUntil: 'domcontentloaded', timeout: 60000 })
await page.waitForURL((u) => u.pathname === '/siswa/login', { timeout: 20000 })
log('tanpa sesi diarahkan ke login', page.url().includes('/siswa/login'))
await waitForHydration(page)

/* 2. PIN salah ditolak */
await page.getByLabel('NIS', { exact: true }).fill(nis)
await page.getByLabel('PIN', { exact: true }).fill('pin-salah')
await page.getByRole('button', { name: 'Masuk' }).click()
try {
  await page.waitForSelector('[role="alert"]', { timeout: 15000 })
  const alert = await page.locator('[role="alert"]').innerText()
  log('PIN salah ditolak', alert.length > 0, alert.trim())
} catch {
  log('PIN salah ditolak', false)
}

/* 3. PIN default (= NIS) -> wajib ganti PIN */
await page.getByLabel('PIN', { exact: true }).fill(nis)
await page.getByRole('button', { name: 'Masuk' }).click()
try {
  await page.waitForURL((u) => u.pathname === '/siswa/ganti-pin', { timeout: 20000 })
  log('login PIN default diarahkan ke ganti PIN', true)
} catch {
  log('login PIN default diarahkan ke ganti PIN', false, page.url())
}

/* 4. Ganti PIN -> masuk beranda */
await page.getByLabel('PIN Saat Ini').fill(nis)
await page.getByLabel('PIN Baru', { exact: true }).fill(pinBaru)
await page.getByLabel('Konfirmasi PIN Baru').fill(pinBaru)
await page.getByRole('button', { name: 'Simpan PIN' }).click()
try {
  await page.waitForURL((u) => u.pathname === '/siswa', { timeout: 20000 })
  await page.waitForFunction((n) => (document.body.innerText || '').includes(n), nama, { timeout: 15000 })
  log('PIN diganti & masuk beranda', true)
} catch {
  log('PIN diganti & masuk beranda', false, page.url())
}

/* 5. Sesi bertahan setelah reload (cookie token) */
await page.goto(`${MOBILE}/siswa`, { waitUntil: 'domcontentloaded', timeout: 60000 })
try {
  await page.waitForFunction((n) => (document.body.innerText || '').includes(n), nama, { timeout: 20000 })
  log('sesi bertahan setelah reload', true)
} catch {
  log('sesi bertahan setelah reload', false, page.url())
}

/* 6. Absensi wajah 1:1 — daftarkan descriptor yang tertangkap lalu lanjutkan */
let registered = false
await page.route('**/api/v1/student/attendance/scan', async (route) => {
  if (!registered) {
    const body = JSON.parse(route.request().postData() || '{}')
    const res = await api(`/students/${studentId}/face-descriptors`, {
      method: 'POST',
      body: JSON.stringify({ descriptor: body.descriptor }),
    })
    registered = res.status === 201
    console.log(`(descriptor terdaftar otomatis: ${registered ? 'ya' : `gagal (${res.status})`})`)
  }
  await route.continue()
})

await page.goto(`${MOBILE}/siswa/scan`, { waitUntil: 'domcontentloaded', timeout: 60000 })
await waitForHydration(page)
await page.waitForFunction(videoReady, { timeout: 30000 }).catch(() => {})
try {
  await page.waitForSelector('[data-testid="mulai-scan"]:not([disabled])', { timeout: 40000 })
  await page.locator('[data-testid="mulai-scan"]').click()
  await page.waitForURL((u) => u.pathname === '/siswa/berhasil', { timeout: 60000 })
  const text = await bodyText(page)
  log('scan wajah mencatat absensi (1:1)', text.includes(nama), text.split('\n').find((l) => l.includes('Jam')) ?? '')
} catch (error) {
  log('scan wajah mencatat absensi (1:1)', false, `${page.url()} — ${error.message}`)
}

/* 7. Riwayat memuat data */
await page.goto(`${MOBILE}/siswa/riwayat`, { waitUntil: 'domcontentloaded', timeout: 60000 })
try {
  await page.waitForFunction(() => document.querySelectorAll('main ul > li').length >= 1, {
    timeout: 25000,
  })
  const rows = await page.locator('main ul > li').count()
  log('riwayat menampilkan catatan absensi', rows >= 1, `${rows} baris`)
} catch (error) {
  log('riwayat menampilkan catatan absensi', false, error.message)
}

/* 8. Profil menampilkan NIS + keluar menghapus sesi */
await page.goto(`${MOBILE}/siswa/profil`, { waitUntil: 'domcontentloaded', timeout: 60000 })
try {
  await page.waitForFunction((n) => (document.body.innerText || '').includes(n), nis, {
    timeout: 25000,
  })
  log('profil menampilkan NIS', true)
  await page.getByRole('button', { name: 'Keluar' }).click()
  await page.waitForURL((u) => u.pathname === '/siswa/login', { timeout: 20000 })
  log('keluar menghapus sesi & kembali ke login', true)
} catch (error) {
  log('profil & keluar', false, error.message)
}

/* 9. Setelah keluar, halaman siswa terkunci lagi */
await page.goto(`${MOBILE}/siswa/riwayat`, { waitUntil: 'domcontentloaded', timeout: 60000 })
try {
  await page.waitForURL((u) => u.pathname === '/siswa/login', { timeout: 20000 })
  log('setelah keluar halaman siswa terkunci', true)
} catch {
  log('setelah keluar halaman siswa terkunci', false, page.url())
}

await ctx.close()
await browser.close()

log('tanpa error JS di halaman siswa', errors.length === 0, errors.slice(0, 3).join(' | '))
console.log(failures === 0 ? '\nSEMUA TEST MOBILE LULUS' : `\n${failures} TEST GAGAL`)
process.exit(failures === 0 ? 0 : 1)
