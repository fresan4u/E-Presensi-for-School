import 'dotenv/config';
import { fileURLToPath } from 'node:url';

const requireEnv = (name, fallback) => process.env[name] ?? fallback;

export const config = {
  port: Number(process.env.PORT || 3005),
  host: requireEnv('HOST', '127.0.0.1'),

  /** Secret bersama dengan backend Laravel (header `x-api-secret`). */
  secret: requireEnv('WA_SECRET', ''),

  /** Folder penyimpanan sesi WhatsApp (credential + pairing). */
  authDir: requireEnv('WA_AUTH_DIR', fileURLToPath(new URL('../auth/', import.meta.url))),

  /** Opsional: nomor 628xxxx untuk pairing code tanpa QR. */
  pairingNumber: requireEnv('WA_PAIRING_NUMBER', '').replace(/\D/g, '') || null,

  /** Berapa lama satu QR berlaku sebelum diganti (ms). */
  qrTimeoutMs: Number(process.env.WA_QR_TIMEOUT_MS || 120_000),

  /** Lokasi berkas gambar QR terbaru (memudahkan scan dari layar lain). */
  qrFile: requireEnv('WA_QR_FILE', fileURLToPath(new URL('../qr.png', import.meta.url))),

  /** Jeda antar pesan keluar (ms) — pelan & konsisten, hindari pembatasan. */
  sendDelayMs: Number(process.env.WA_SEND_DELAY_MS || 1500),
};

/** Nomor tujuan → JID WhatsApp personal (628xxx@s.whatsapp.net). */
export function toJid(phone) {
  const digits = String(phone ?? '').replace(/\D/g, '');
  return `${digits}@s.whatsapp.net`;
}
