# Image service WhatsApp (Baileys) untuk Railway.
#
# Catatan Railway:
# - Mount volume ke /data supaya folder auth/ & QR tidak hilang saat redeploy.
# - Set HOST=0.0.0.0 agar bisa diakses backend lewat private networking.
# - Jangan scale lebih dari 1 replica: sesi WhatsApp bersifat stateful.

FROM node:22-bookworm-slim

ENV NODE_ENV=production
WORKDIR /app

# Install dependency lebih dulu agar layer cache tetap dipakai saat src berubah.
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --no-audit --no-fund \
    && npm cache clean --force

COPY src ./src

# Default khusus container/Railway. Semua bisa dioverride lewat env Railway.
ENV PORT=3005
ENV HOST=0.0.0.0
ENV WA_AUTH_DIR=/data/auth
ENV WA_QR_FILE=/data/qr.png

EXPOSE 3005

CMD ["node", "src/index.js"]
