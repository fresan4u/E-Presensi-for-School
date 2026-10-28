import makeWASocket, {
  Browsers,
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import { rename } from 'node:fs/promises';
import pino from 'pino';
import QRCode from 'qrcode';
import qrcode from 'qrcode-terminal';
import { config } from './config.js';

const logger = pino({ level: process.env.WA_LOG_LEVEL || 'error' });

/** Arti kode putus koneksi yang paling sering muncul saat pairing. */
const DISCONNECT_HINT = {
  401: 'sesi tidak valid / login ditolak (logged out)',
  403: 'ditolak server — perangkat mungkin sudah penuh atau nomor tidak valid',
  408: 'QR/pairing code kedaluwarsa atau koneksi lambat',
  409: 'sesi lain sedang menggantikan koneksi ini (reconnect)',
  428: 'koneksi ditutup',
  440: 'login ditolak karena versi client terlalu lama',
  500: 'server WhatsApp bermasalah, coba lagi sebentar lagi',
  515: 'butuh mulai ulang koneksi (normal setelah pairing)',
};

let sock = null;
let connected = false;
let me = null;
let latestQr = null;
let restartAttempts = 0;
let pairingRequested = false;

export function isReady() {
  return connected && sock !== null;
}

/** QR terbaru untuk menautkan perangkat (null bila sudah terhubung). */
export function getQr() {
  return latestQr;
}

/** Socket aktif saat ini (bisa berubah setelah reconnect). */
export function getSock() {
  return sock;
}

export function whoAmI() {
  return me;
}

/**
 * Minta pairing code (sekali per siklus koneksi) untuk WA_PAIRING_NUMBER.
 * Dipanggil ulang otomatis bila koneksi terputus lalu tersambung kembali.
 */
async function requestPairingCode(socket, state) {
  if (pairingRequested || state.creds.registered) {
    return;
  }

  pairingRequested = true;

  try {
    const code = await socket.requestPairingCode(config.pairingNumber);
    console.log(`\n[wa-bot] Pairing code untuk ${config.pairingNumber}: ${code}`);
    console.log(
      '[wa-bot] Di HP nomor tersebut: WhatsApp > Perangkat Tertaut > Tautkan Perangkat > Tautkan dengan nomor telepon.\n',
    );
  } catch (error) {
    pairingRequested = false;
    console.error('[wa-bot] Gagal meminta pairing code:', error.message);
  }
}

/** Tampilkan QR di terminal dan simpan versi PNG-nya ke berkas. */
async function renderQr(qr) {
  qrcode.generate(qr, { small: true });
  console.log(`[wa-bot] QR juga tersimpan sebagai gambar: ${config.qrFile}\n`);

  try {
    // Tulis dulu ke berkas sementara lalu pindahkan, supaya pembaca tidak
    // pernah menemukan gambar yang masih setengah jadi.
    const tmp = `${config.qrFile}.tmp`;
    await QRCode.toFile(tmp, qr, { width: 768, margin: 4 });
    await rename(tmp, config.qrFile);
  } catch (error) {
    console.error('[wa-bot] Gagal menyimpan QR sebagai gambar:', error.message);
  }
}

/** Cetak alasan putus koneksi selengkap mungkin supaya mudah didiagnosis. */
function logDisconnect(lastDisconnect) {
  const statusCode = lastDisconnect?.error?.output?.statusCode;
  const message = lastDisconnect?.error?.message ?? 'tanpa pesan';
  const payload = lastDisconnect?.error?.output?.payload;
  const hint = DISCONNECT_HINT[statusCode] ?? 'penyebab tidak dikenali';

  console.error(`\n[wa-bot] Koneksi terputus — status ${statusCode ?? '?'} (${hint})`);
  console.error(`[wa-bot] Pesan: ${message}`);

  if (payload) {
    console.error(`[wa-bot] Detail server: ${JSON.stringify(payload)}`);
  }

  return statusCode;
}

/**
 * Jalankan sesi WhatsApp (sekali per proses). Cetak QR di terminal saat
 * belum ter-login, atau minta pairing code bila WA_PAIRING_NUMBER diisi.
 * Koneksi yang putus otomatis dihubungkan ulang dengan jeda bertahap.
 */
export async function startSession() {
  if (sock) {
    return sock;
  }

  const { state, saveCreds } = await useMultiFileAuthState(config.authDir);
  const { version } = await fetchLatestBaileysVersion();

  console.log(`[wa-bot] Versi WhatsApp Web yang dipakai: ${version.join('.')}`);

  sock = makeWASocket({
    auth: state,
    version,
    logger,
    browser: Browsers.macOS('Desktop'),
    markOnlineOnConnect: false,
    syncFullHistory: false,
    generateHighQualityLinkPreview: false,
    // Beri waktu lebih lama untuk scan QR (default Baileys hanya 60 detik).
    qrTimeout: config.qrTimeoutMs,
  });

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    // Belum ter-login: tampilkan QR, atau minta pairing code sekali per siklus.
    if (qr && !state.creds.registered) {
      latestQr = qr;

      if (config.pairingNumber) {
        await requestPairingCode(sock, state);
      } else {
        console.log('\n[wa-bot] Scan QR ini dari WhatsApp (Perangkat Tertaut):\n');
        await renderQr(qr);
      }
    }

    if (connection === 'open') {
      restartAttempts = 0;
      connected = true;
      latestQr = null;
      me = sock.user?.id ?? null;
      console.log(`\n[wa-bot] ✅ Terhubung ke WhatsApp sebagai ${sock.user?.name ?? '?'} (${me})`);
    }

    if (connection === 'close') {
      connected = false;
      // Siklus koneksi baru -> pairing code (bila ada) boleh diminta lagi.
      pairingRequested = false;

      const statusCode = logDisconnect(lastDisconnect);

      if (statusCode === DisconnectReason.loggedOut) {
        console.error(
          `[wa-bot] Sesi keluar. Hapus folder ${config.authDir} lalu jalankan ulang untuk pairing baru.`,
        );
        sock = null;
        return;
      }

      restartAttempts += 1;
      const delayMs = Math.min(30_000, 2_000 * restartAttempts);
      console.warn(`[wa-bot] Menyambung ulang dalam ${delayMs}ms (percobaan ${restartAttempts})...`);
      setTimeout(() => {
        sock = null;
        startSession().catch((error) => console.error('[wa-bot] Gagal menyambung ulang:', error.message));
      }, delayMs);
    }
  });

  return sock;
}
