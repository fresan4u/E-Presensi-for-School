#!/usr/bin/env bash
# Picu deploy produksi Vercel dari commit Git terakhir (cadangan auto-deploy).
#
# Kenapa begini: kalau webhook GitHub -> Vercel sedang tidak jalan (mis. hook
# di sisi GitHub dinonaktifkan karena gagal kirim), `vercel --prod` masih bisa
# dipakai tapi meng-upload file lokal. Script ini sebaliknya memakai deployment
# API dengan `gitSource`, sehingga Vercel membangun DARI commit di GitHub —
# hasilnya identik dengan auto-deploy (deployment tetap ter-link ke commit).
#
# Pakai: ./deploy-vercel-git.sh [ref]
#   ref  opsional, default `main` (branch atau SHA).
set -euo pipefail

REF="${1:-main}"
TEAM="team_RNcLdr6JsRqs1tgh2nhLzAgE"

# project-name:project-id
PROJECTS=(
  "presensi-admin:prj_gSZyQgujaGNlMEvMzcAxffuM3VjM"
  "presensi-petugas:prj_4cLyww9bQ9OpyHkAP7zgVGSqFKbP"
  "presensi-mobile:prj_SjkJWp4pSbtOgM71yp4CK3jgEm1Z"
)

REPO_ID=1420321455
TOKEN="$(python3 -c "import json,os; print(json.load(open(os.path.expanduser('~/.local/share/com.vercel.cli/auth.json')))['token'])")"

# Resolve ref -> SHA supaya pengiriman selalu memakai commit pasti (bukan branch
# yang bisa bergerak).
cd "$(dirname "${BASH_SOURCE[0]}")"
SHA="$(git rev-parse "$REF")"
echo "ref=$REF sha=${SHA:0:7}"

deploy_one() {
  local name="$1" pid="$2"
  local out
  out="$(curl -s -X POST "https://api.vercel.com/v13/deployments?teamId=$TEAM&forceNew=1" \
    -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
    -d "{\"name\":\"$name\",\"project\":\"$pid\",\"target\":\"production\",\"gitSource\":{\"type\":\"github\",\"repoId\":$REPO_ID,\"ref\":\"$REF\",\"sha\":\"$SHA\"}}")"
  echo "$out" | python3 -c "
import json,sys
d=json.load(sys.stdin)
err=d.get('error')
print('  $name ->', ('ERROR: '+str(err)) if err else (d.get('url') or 'ok'))
"
}

for entry in "${PROJECTS[@]}"; do
  deploy_one "${entry%%:*}" "${entry##*:}"
done

echo "selesai. Cek status: https://vercel.com/snazyy-s/<project>/deployments"
