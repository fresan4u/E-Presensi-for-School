#!/usr/bin/env bash
# Hubungkan project Vercel + Railway ke repo GitHub supaya cukup `git push`
# untuk memicu build & deploy otomatis.
#
# Prasyarat (sekali saja, lewat browser):
#   - Vercel GitHub App terinstall di repo  : https://github.com/apps/vercel
#   - Railway GitHub App terinstall di repo : https://railway.com/dashboard
#
# Pakai: ./setup-git-autodeploy.sh [--verify]
set -euo pipefail

REPO="fresan4u/E-Presensi-for-School"
BRANCH="main"
VERCEL_PROJECTS=(presensi-admin presensi-petugas presensi-mobile)
RAILWAY_PROJECT="presensi-api"
RAILWAY_SERVICE="presensi-api"
RAILWAY_PROJECT_ID="a6b87a43-f90d-4b69-8340-6ad3cc46f0a5"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

TOKEN="$(python3 -c "import json,os; print(json.load(open(os.path.expanduser('~/.local/share/com.vercel.cli/auth.json')))['token'])")"
TEAM_ID="$(curl -s "https://api.vercel.com/v2/teams" -H "Authorization: Bearer $TOKEN" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['teams'][0]['id'])")"

vercel_api() {
  local method="$1" path="$2" body="${3:-}"
  if [ -n "$body" ]; then
    curl -s -X "$method" "https://api.vercel.com${path}?teamId=${TEAM_ID}" \
      -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "$body"
  else
    curl -s -X "$method" "https://api.vercel.com${path}?teamId=${TEAM_ID}" \
      -H "Authorization: Bearer $TOKEN"
  fi
}

project_id() {
  vercel_api GET "/v9/projects/$1" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])"
}

link_vercel_project() {
  local name="$1" pid
  pid="$(project_id "$name")"

  local response
  response="$(vercel_api POST "/v9/projects/${pid}/link" \
    "{\"type\":\"github\",\"repo\":\"${REPO}\",\"productionBranch\":\"${BRANCH}\"}")"

  if printf '%s' "$response" | grep -q '"error"'; then
    printf '  %-18s GAGAL: %s\n' "$name" \
      "$(printf '%s' "$response" | python3 -c "import sys,json; print(json.load(sys.stdin)['error'].get('message',''))" 2>/dev/null)"
    return 1
  fi

  printf '  %-18s OK (terhubung ke %s@%s)\n' "$name" "$BRANCH" "$REPO"
}

link_railway_service() {
  if railway service source connect --repo "$REPO" --branch "$BRANCH" \
      --service "$RAILWAY_SERVICE" --json >/tmp/railway-source.out 2>&1; then
    printf '  %-18s OK (terhubung ke %s@%s)\n' "$RAILWAY_SERVICE" "$BRANCH" "$REPO"
  else
    printf '  %-18s GAGAL: %s\n' "$RAILWAY_SERVICE" "$(tail -1 /tmp/railway-source.out)"
    return 1
  fi
}

if [ "${1:-}" = "--verify" ]; then
  echo "Vercel:"
  for name in "${VERCEL_PROJECTS[@]}"; do
    vercel_api GET "/v9/projects/$name" | python3 -c "
import sys, json
d = json.load(sys.stdin)
link = d.get('link') or {}
repo = link.get('repo') or '-'
print(f\"  {d.get('name'):18} framework={d.get('framework')} root={d.get('rootDirectory')} git={repo}\")
"
  done
  echo "Railway:"
  railway api 'query { environments(projectId: "'"$RAILWAY_PROJECT_ID"'") { edges { node { name serviceInstances { edges { node { serviceName rootDirectory dockerfilePath } } } } } } }' 2>/dev/null | python3 -c "
import sys, json
d = json.load(sys.stdin)
for e in d.get('data', {}).get('environments', {}).get('edges', []):
    for si in e['node'].get('serviceInstances', {}).get('edges', []):
        s = si['node']
        print(f\"  {s.get('serviceName'):18} root={s.get('rootDirectory')} dockerfile={s.get('dockerfilePath')}\")
" || echo "  (gagal query Railway API)"
  exit 0
fi

echo "Menghubungkan project Vercel ke ${REPO}@${BRANCH} ..."
vercel_failed=0
for name in "${VERCEL_PROJECTS[@]}"; do
  link_vercel_project "$name" || vercel_failed=1
done

echo "Menghubungkan service Railway ke ${REPO}@${BRANCH} ..."
railway_failed=0
link_railway_service || railway_failed=1

echo
if [ "$vercel_failed" -eq 0 ] && [ "$railway_failed" -eq 0 ]; then
  echo "Semua terhubung. Sekarang cukup:"
  echo "  git add . && git commit -m '...' && git push origin $BRANCH"
else
  cat <<'EOF'
Ada yang belum terhubung. Penyebab & solusinya:

  Vercel  "install the GitHub integration first"
          -> Buka https://github.com/apps/vercel, install ke repo ini
             (pilih "Only select repositories" -> centang repo ini).
             Lalu ulangi: ./setup-git-autodeploy.sh

  Railway "Deploys have been paused temporarily"
          -> Insiden global Railway (status.railway.com). Coba lagi nanti;
             service yang sudah jalan tidak terpengaruh.
             railway service source connect --repo fresan4u/E-Presensi-for-School \
               --branch main --service presensi-api
EOF
fi
exit $((vercel_failed || railway_failed))
