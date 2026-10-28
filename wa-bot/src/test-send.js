/**
 * Kirim pesan percobaan langsung dari terminal (sesi harus sudah login):
 *   npm run test-send -- 6281234567890 "Halo, ini pesan tes."
 */
import { isReady, startSession } from './session.js';
import { sendText } from './send.js';

const [, , phone, ...words] = process.argv;
const text = words.join(' ') || 'Pesan tes dari Presensi WA Bot ✅';

if (!phone) {
  console.error('Pakai: npm run test-send -- 6281234567890 "pesan"');
  process.exit(1);
}

const sock = await startSession();

const deadline = Date.now() + 120_000;
while (!isReady() && Date.now() < deadline) {
  await new Promise((resolve) => setTimeout(resolve, 500));
}

if (!isReady()) {
  console.error('[test-send] WhatsApp tidak terhubung dalam 120 detik — cek QR / WA_PAIRING_NUMBER.');
  process.exit(1);
}

const result = await sendText(sock, phone, text);
console.log(`[test-send] Terkirim ke ${phone}: ${result?.key?.id ?? '(id tidak diketahui)'}`);
process.exit(0);
