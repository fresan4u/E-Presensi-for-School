#!/usr/bin/env bash
# Deploy satu app Nuxt dari monorepo ke Vercel (dipakai untuk demo).
#
# Kenapa begini: Vercel harus meng-upload SELURUH monorepo supaya workspace
# @presensi/shared ikut terpasang, lalu Root Directory project menunjuk ke
# subfolder app. Build command dijalankan lewat workspace npm.
#
# Pakai: ./deploy-vercel.sh <Presensi_admin|Presensi_petugas|Presensi_mobile> <project-name> [env KEY=VALUE ...]
set -euo pipefail

APP_DIR="$1"; PROJECT="$2"; shift 2
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKSPACE="$(python3 -c "import json,sys; print(json.load(open(sys.argv[1]+'/package.json'))['name'])" "$ROOT/$APP_DIR")"
TOKEN="$(python3 -c "import json,os; print(json.load(open(os.path.expanduser('~/.local/share/com.vercel.cli/auth.json')))['token'])")"

cd "$ROOT"
mkdir -p .vercel
vercel link --yes --project "$PROJECT" >/dev/null 2>&1 || true

PID="$(python3 -c "import json; print(json.load(open('.vercel/project.json'))['projectId'])")"
TID="$(python3 -c "import json; print(json.load(open('.vercel/project.json'))['orgId'])")"

vercel_api() {
  curl -s -X PATCH "https://api.vercel.com/v9/projects/$PID?teamId=$TID" \
    -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "$1"
}

# Root Directory menunjuk subfolder app; install & build lewat workspace npm
# dari root monorepo. Proteksi deployment dimatikan supaya demo bisa dibuka publik.
vercel_api "{\"rootDirectory\":\"$APP_DIR\",\"buildCommand\":\"npm run build --workspace $WORKSPACE\",\"installCommand\":null,\"ssoProtection\":null,\"passwordProtection\":null}" \
  | python3 -c "import sys,json; d=json.load(sys.stdin); print('settings -> root:',d.get('rootDirectory'),'| build:',d.get('buildCommand'),'| err:',d.get('error'))"

# Env var: dikirim lewat API (CLI `vercel env add` sempat salah menyimpan value).
for kv in "$@"; do
  KEY="${kv%%=*}"; VALUE="${kv#*=}"
  curl -s -X POST "https://api.vercel.com/v10/projects/$PID/env?teamId=$TID&upsert=true" \
    -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
    -d "{\"key\":\"$KEY\",\"value\":\"$VALUE\",\"type\":\"plain\",\"target\":[\"production\",\"preview\",\"development\"]}" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print('env $KEY ->', 'ok' if not d.get('error') else d['error'])"
done

echo "deploying $APP_DIR ..."
vercel --prod --yes 2>&1 | tail -5
