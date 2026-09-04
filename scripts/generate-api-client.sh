#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SPEC_PATH="${ROOT_DIR}/app/storage/app/openapi.json"
OUT_DIR="${ROOT_DIR}/web/src/api/generated"

if [[ ! -f "${SPEC_PATH}" ]]; then
  cat >&2 <<'MSG'
OpenAPI spec belum ada.
Jalankan setelah backend dependency terpasang:
  docker compose run --rm app php artisan scramble:export --path=storage/app/openapi.json
Lalu ulangi:
  make api-generate
MSG
  exit 1
fi

mkdir -p "${OUT_DIR}"
cd "${ROOT_DIR}/web"
npx openapi-typescript "${SPEC_PATH}" -o "src/api/generated/schema.ts"
echo "Generated API client types: web/src/api/generated/schema.ts"
