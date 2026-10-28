import { config, toJid } from './config.js';

/**
 * Antrean kirim berurutan: setiap pesan menunggu giliran + jeda
 * WA_SEND_DELAY_MS supaya terhindar dari pembatasan/flag spam WhatsApp.
 */
let chain = Promise.resolve();

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export function enqueueSend(task) {
  const run = chain.then(async () => {
    await delay(config.sendDelayMs);
    return task();
  });

  // Lanjutkan rantai walau task gagal (error dikembalikan ke pemanggil).
  chain = run.catch(() => {});
  return run;
}

/** Kirim teks saja. */
export async function sendText(sock, phone, text) {
  return sock.sendMessage(toJid(phone), { text });
}

/** Kirim foto (Buffer JPEG/PNG) dengan caption teks. */
export async function sendPhoto(sock, phone, photoBuffer, caption) {
  return sock.sendMessage(toJid(phone), {
    image: photoBuffer,
    caption,
    mimetype: 'image/jpeg',
  });
}
