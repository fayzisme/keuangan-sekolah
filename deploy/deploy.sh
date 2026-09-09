#!/usr/bin/env bash
# =====================================================================
# deploy.sh — Build + recreate container School Finance (one-shot).
#
# PENGGUNAAN:
#   ./deploy/deploy.sh                # rebuild API + UI, recreate containers
#   ./deploy/deploy.sh --skip-ui      # hanya image API (frontend tidak berubah)
#   ./deploy/deploy.sh --skip-api     # hanya image UI (backend tidak berubah)
#   ./deploy/deploy.sh --port 8082    # port host kustom (default: 8082)
#   ./deploy/deploy.sh --tag pilot    # prefiks tag image (default: pilot)
#   ./deploy/deploy.sh --prune        # hapus image sha-tagged lama (>3)
#
# ENV:
#   .env di root repo (gitignored) akan di-source jika ada (production).
#   Tanpa .env: nilai env dibaca dari container 'app' yang sedang berjalan
#   (alur pilot — secret tidak pernah masuk git atau log terminal).
#
# ⚠️  Menghapus & membuat ulang container LIVE (docker rm -f).
#   Jalankan dengan risiko sendiri, sebaiknya dengan persetujuan eksplisit.
# =====================================================================
set -euo pipefail

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info() { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()  { echo -e "${RED}[ERROR]${NC} $*" >&2; }

# ---------------------------------------------------------------- argumen
PORT=8082
TAG=pilot
SKIP_UI=0
SKIP_API=0
PRUNE=0

usage() {
  sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'
  exit 1
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-ui)   SKIP_UI=1;;
    --skip-api)  SKIP_API=1;;
    --port)      PORT="$2"; shift;;
    --tag)       TAG="$2"; shift;;
    --prune)     PRUNE=1;;
    -h|--help)   usage;;
    *) err "Opsi tidak dikenal: $1"; usage;;
  esac
  shift
done

# ---------------------------------------------------------------- root repo
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

SHORT_SHA="$(git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"
info "=== Deploy School Finance @ ${SHORT_SHA} (tag: ${TAG}, port: ${PORT}) ==="

# ---------------------------------------------------------------- sumber env
if [[ -f .env ]]; then
  info "Sumber env: .env (root repo)."
  set -a; source .env; set +a
else
  warn "./.env tidak ditemukan — baca env dari container 'app' yang live (pilot)."
  RUNNING="$(docker inspect -f '{{.State.Running}}' app 2>/dev/null || echo 'missing')"
  [[ "$RUNNING" == "true" ]] || {
    err "Container 'app' tidak berjalan dan tidak ada .env. Buat .env (lihat .env.example)."
    exit 1
  }
  while IFS= read -r line; do
    [[ "$line" == *=* ]] && export "$line"
  done < <(docker inspect app --format '{{range .Config.Env}}{{println .}}{{end}}' \
           | grep -E '^(APP_ENV|APP_KEY|PLATFORM_KEY|DB_|REDIS_|CACHE_STORE|QUEUE_CONNECTION|SESSION_DRIVER|DB_CONNECTION|APP_DEBUG)=' || true)
fi

# ---------------------------------------------------------------- default (pilot)
APP_ENV="${APP_ENV:-pilot}"
APP_DEBUG="${APP_DEBUG:-true}"
DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_HOST="${DB_HOST:-school-pg}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-school_finance}"
DB_USERNAME="${DB_USERNAME:-school_finance}"
DB_PASSWORD="${DB_PASSWORD:-}"
REDIS_HOST="${REDIS_HOST:-school-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"
REDIS_PASSWORD="${REDIS_PASSWORD:-}"
CACHE_STORE="${CACHE_STORE:-file}"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
SESSION_DRIVER="${SESSION_DRIVER:-file}"
APP_KEY="${APP_KEY:-}"
PLATFORM_KEY="${PLATFORM_KEY:-}"

# validasi secret yang wajib ada
[[ -n "$APP_KEY" ]] || { err "APP_KEY kosong — isi .env dulu."; exit 1; }
[[ -n "$DB_PASSWORD" ]] || { err "DB_PASSWORD kosong — isi .env dulu."; exit 1; }

# ---------------------------------------------------------------- network
if ! docker network inspect school-net >/dev/null 2>&1; then
  info "Network 'school-net' belum ada — membuat..."
  docker network create school-net
fi

# ---------------------------------------------------------------- build image API
if [[ "$SKIP_API" -ne 1 ]]; then
  info "Build image API (school-app:${TAG}, context=root repo)..."
  docker build -t "school-app:${TAG}" -t "school-app:${TAG}-${SHORT_SHA}" -f app/Dockerfile .
fi

# ---------------------------------------------------------------- build image UI
if [[ "$SKIP_UI" -ne 1 ]]; then
  if command -v npm >/dev/null 2>&1 && [[ -d web/node_modules ]]; then
    info "Build frontend (npm run build)..."
    ( cd web && npm run build )
  elif [[ -f web/dist/index.html ]]; then
    warn "npm/node_modules tidak tersedia — pakai web/dist yang sudah ada."
  else
    err "web/dist tidak ada dan npm tidak tersedia. Tidak bisa build UI."
    exit 1
  fi
  info "Build image UI (school-nginx:${TAG})..."
  docker build -t "school-nginx:${TAG}" -t "school-nginx:${TAG}-${SHORT_SHA}" -f deploy/Dockerfile.web .
fi

# ---------------------------------------------------------------- recreate app
info "Recreate container 'app' (image school-app:${TAG})..."
docker rm -f app >/dev/null 2>&1 || true
docker run -d --name app --network school-net --restart unless-stopped \
  -e APP_ENV="$APP_ENV" \
  -e APP_KEY="$APP_KEY" \
  -e PLATFORM_KEY="$PLATFORM_KEY" \
  -e APP_DEBUG="$APP_DEBUG" \
  -e DB_CONNECTION="$DB_CONNECTION" \
  -e DB_HOST="$DB_HOST" \
  -e DB_PORT="$DB_PORT" \
  -e DB_DATABASE="$DB_DATABASE" \
  -e DB_USERNAME="$DB_USERNAME" \
  -e DB_PASSWORD="$DB_PASSWORD" \
  -e REDIS_HOST="$REDIS_HOST" \
  -e REDIS_PORT="$REDIS_PORT" \
  -e REDIS_PASSWORD="$REDIS_PASSWORD" \
  -e CACHE_STORE="$CACHE_STORE" \
  -e QUEUE_CONNECTION="$QUEUE_CONNECTION" \
  -e SESSION_DRIVER="$SESSION_DRIVER" \
  "school-app:${TAG}" >/dev/null

# ---------------------------------------------------------------- recreate nginx
info "Recreate container 'school-nginx' (image school-nginx:${TAG}, :${PORT}->80)..."
docker rm -f school-nginx >/dev/null 2>&1 || true
docker run -d --name school-nginx --network school-net --restart unless-stopped \
  -p "${PORT}:80" \
  "school-nginx:${TAG}" >/dev/null

# ---------------------------------------------------------------- healthcheck
info "Health check (maks 60 detik)..."
OK=0
for _ in $(seq 1 30); do
  if curl -fsS "http://127.0.0.1:${PORT}/healthz" >/dev/null 2>&1; then OK=1; break; fi
  sleep 2
done
if [[ "$OK" -ne 1 ]]; then
  err "Healthz tidak merespons setelah 60 detik — cek: docker logs school-nginx / docker logs app"
  exit 1
fi
info "Status SPA: $(curl -fsS -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/")"
info "healthz:     $(curl -fsS "http://127.0.0.1:${PORT}/healthz")"

# ---------------------------------------------------------------- cleanup (opsional)
if [[ "$PRUNE" -eq 1 ]]; then
  info "Prune image sha-tagged lama (pertahankan 3 terbaru per service)..."
  docker image ls --format '{{.Repository}}:{{.Tag}}' \
    | grep -E "school-(app|nginx):${TAG}-[0-9a-f]{7}" \
    | sort -u | head -n -6 | while IFS=: read -r img; do
        docker rmi "$img" >/dev/null 2>&1 || true
      done
fi

# penanda sukses (tanpa secret di output)
echo "DEPLOY_OK:${SHORT_SHA}"