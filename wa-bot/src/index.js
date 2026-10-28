import crypto from 'node:crypto';
import express from 'express';
import { config } from './config.js';
import { enqueueSend, sendPhoto, sendText } from './send.js';
import { getQr, getSock, isReady, startSession, whoAmI } from './session.js';

const app = express();
app.use(express.json({ limit: '8mb' }));

/** Bandingkan secret secara timing-safe. */
function secretMatches(provided) {
  if (!config.secret) {
    return false;
  }

  const a = Buffer.from(String(provided ?? ''));
  const b = Buffer.from(config.secret);

  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

function requireSecret(req, res, next) {
  if (!config.secret) {
    return res.status(500).json({ ok: false, error: 'WA_SECRET belum diisi di wa-bot/.env' });
  }

  if (!secretMatches(req.get('x-api-secret'))) {
    return res.status(401).json({ ok: false, error: 'x-api-secret tidak cocok' });
  }

  next();
}

/** Status kesiswaan service — dipanggil `php artisan wa:status` & uptime check. */
app.get('/health', (req, res) => {
  res.json({ ok: true, connected: isReady(), user: whoAmI() });
});

/**
 * QR terbaru untuk menautkan perangkat baru (dilindungi secret karena
 * QR = akses penuh ke akun). Berguna kalau terminal wa-bot tidak terlihat.
 */
app.get('/qr', requireSecret, async (req, res) => {
  if (isReady()) {
    return res.json({ ok: true, connected: true, qr: null, qr_file: config.qrFile });
  }

  const qr = getQr();

  if (!qr) {
    return res.status(503).json({
      ok: false,
      connected: false,
      qr: null,
      qr_file: config.qrFile,
      error: 'QR belum tersedia — tunggu beberapa detik lalu coba lagi',
    });
  }

  res.json({ ok: true, connected: false, qr, qr_file: config.qrFile });
});

/**
 * Terima satu pesan dari backend Laravel:
 * { id, to, text, photo_base64? } → kirim ke WhatsApp, balas { ok, wamid }.
 */
app.post('/send', requireSecret, (req, res) => {
  const { id, to, text, photo_base64 } = req.body ?? {};

  const digits = String(to ?? '').replace(/\D/g, '');
  if (!/^\d{9,15}$/.test(digits)) {
    return res.status(422).json({ ok: false, error: `nomor tujuan tidak valid: ${to}` });
  }

  if (typeof text !== 'string' || text.trim() === '') {
    return res.status(422).json({ ok: false, error: 'text wajib diisi' });
  }

  if (photo_base64 !== undefined && typeof photo_base64 !== 'string') {
    return res.status(422).json({ ok: false, error: 'photo_base64 harus string base64' });
  }

  if (!isReady()) {
    return res.status(503).json({ ok: false, error: 'WhatsApp belum terhubung — scan QR dulu (lihat terminal wa-bot)' });
  }

  const sock = getSock();
  enqueueSend(async () => {
    if (photo_base64) {
      const buffer = Buffer.from(photo_base64, 'base64');
      return sendPhoto(sock, digits, buffer, text);
    }
    return sendText(sock, digits, text);
  })
    .then((result) => {
      const wamid = result?.key?.id ?? null;
      console.log(`[wa-bot] Terkirim #${id ?? '-'} → ${digits} (${photo_base64 ? 'foto+teks' : 'teks'}) ${wamid ?? ''}`);
      res.json({ ok: true, wamid });
    })
    .catch((error) => {
      console.error(`[wa-bot] Gagal kirim #${id ?? '-'} → ${digits}:`, error.message);
      res.status(502).json({ ok: false, error: error.message });
    });
});

// Abaikan body JSON yang rusak supaya service tidak crash.
app.use((err, req, res, next) => {
  if (err?.type === 'entity.parse.failed') {
    return res.status(400).json({ ok: false, error: 'JSON tidak valid' });
  }
  console.error('[wa-bot] Error tak terduga:', err);
  res.status(500).json({ ok: false, error: 'internal error' });
  next?.();
});

startSession()
  .then(() => {
    app.listen(config.port, config.host, () => {
      console.log(`[wa-bot] HTTP siap di http://${config.host}:${config.port}`);
      if (!config.secret) {
        console.warn('[wa-bot] PERINGATAN: WA_SECRET kosong — endpoint /send akan menolak semua permintaan.');
      }
      if (config.pairingNumber) {
        console.log(`[wa-bot] Mode pairing code: ${config.pairingNumber}`);
      } else {
        console.log(`[wa-bot] Mode QR — QR juga ditulis ke ${config.qrFile} (endpoint: GET /qr)`);
      }
    });
  })
  .catch((error) => {
    console.error('[wa-bot] Gagal memulai sesi:', error);
    process.exit(1);
  });
