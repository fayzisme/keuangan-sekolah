#!/usr/bin/env bash
# =====================================================================
# backup.sh — backup PostgreSQL harian via docker compose.
# PENTING: cadangan tanpa uji-restore bukan cadangan.
# Uji restore bulanan: .github/checklist atau runbook DEVOPS.md.
# =====================================================================
set -euo pipefail

cd "$(dirname "$0")/.."          # ke root repo (tempat .env & compose berada)

# Baca .env untuk kredensial DB
if [[ -f .env ]]; then
  set -a; source .env; set +a
else
  echo "❌ .env tidak ditemukan di $(pwd)"; exit 1
fi

BACKUP_DIR="${BACKUP_DIR:-/var/backups/school-finance}"
STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="${BACKUP_DIR}/school-${STAMP}.sql.gz"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-30}"

mkdir -p "${BACKUP_DIR}"

echo "▶ Backup database ${DB_DATABASE:-school_finance} ..."
docker compose exec -T \
  -e PGPASSWORD="${DB_PASSWORD}" \
  postgres pg_dump -U "${DB_USERNAME:-school_finance}" -d "${DB_DATABASE:-school_finance}" \
  --format=plain --no-owner --no-privileges \
  | gzip > "${FILE}"

# Opsional: enkripsi file (GPG/age). Aktifkan bila GPG_KEY_ID disetel.
# if [[ -n "${GPG_KEY_ID:-}" ]]; then
#   gpg --yes --encrypt --recipient "${GPG_KEY_ID}" "${FILE}" && rm -f "${FILE}"
# fi

echo "✅ Backup selesai: ${FILE} ($(du -h "${FILE}" | cut -f1))"

# Retensi: hapus backup harian lebih dari KEEP_DAYS
find "${BACKUP_DIR}" -name 'school-*.sql.gz' -mtime "+${KEEP_DAYS}" -delete
echo "🧹 Retention: sisa backup harian ${KEEP_DAYS} hari."

# Cek integritas cepat (gzip -t) — gagal akan exit non-zero
gzip -t "${FILE}" && echo "🔍 Integritas file OK."