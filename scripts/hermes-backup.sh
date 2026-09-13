#!/usr/bin/env bash
# ============================================================================
# hermes-backup.sh — Backup aman data persisten Hermes di VPS
# Repo: keuangan-sekolah · docs/ops/hermes-backup.md
#
# PRINSIP:
#   1. TIDAK mematikan gateway (zero-downtime) saat backup.
#   2. Database SQLite WAL di-backup via `sqlite3 .backup` (konsisten walau aktif).
#   3. Selalu verifikasi hasil backup (ukuran > 0, sqlite integrity check).
#   4. Rotasi otomatis: simpan N backup terakhir saja.
#   5. Bisa dijadwalkan via cron TANPA password (pemakaian dari dalam container).
#
# CUSTOMISASI (edit 3 variabel di bawah):
#   BACKUP_ROOT  : tujuan file backup (default: subfolder /backup di dalam /opt/data,
#                  BISA diubah ke mount VPS lain, misal /mnt/backup).
#   KEEP         : berapa backup terakhir yang dipertahankan.
#   LOG_TO_FILE  : 1 = tulis log ke file, 0 = stdout saja.
# ============================================================================
set -euo pipefail

# --- Konfigurasi ------------------------------------------------------------
DATA_ROOT="${HERMES_HOME:-/opt/data}"
BACKUP_ROOT="${BACKUP_ROOT:-${DATA_ROOT}/_backups}"
KEEP="${KEEP:-7}"
LOG_TO_FILE="${LOG_TO_FILE:-0}"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="${BACKUP_ROOT}/hermes-${STAMP}"
LOG_FILE="${BACKUP_ROOT}/backup.log"

# List data yang di-backup (path relatif terhadap DATA_ROOT).
# NOTE: .env DIKECUALIKAN dari archive default demi keamanan → diberi suffix .env.bak
#       yang sudah ada; kalau mau rahasia ikut ter-backup, tambahkan "--include=.env"
#       pada tar di bawah (risiko: file backup berisi credential — enkripsi dulu!).
BACKUP_ITEMS=(
  config.yaml
  state.db
  projects.db
  kanban.db
  response_store.db
  verification_evidence.db
  sessions
  memories
  gateway_state.json
  gateway
  state
  cron
  skills
  plugins
  logs
  channel_directory.json
  discord_threads.json
  school-finance-system
)

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
start_log() {
  if [ "$LOG_TO_FILE" = "1" ]; then
    exec > >(tee -a "$LOG_FILE") 2>&1
  fi
}

# --- Persiapan --------------------------------------------------------------
mkdir -p "$BACKUP_DIR"
start_log
log "== Hermes backup dimulai =="
log "Data root : $DATA_ROOT"
log "Tujuan    : $BACKUP_DIR"

if [ ! -d "$DATA_ROOT" ]; then
  log "ERROR: $DATA_ROOT tidak ada. Abort."
  exit 1
fi

# --- 1) Backup SQLite secara konsisten (walau gateway aktif) ----------------
# SQLite WAL: membuka koneksi tanpa mematikan writer. `.backup` menghasilkan
# file yang konsisten pada satu titik waktu tertentu.
log "Mem-backup database SQLite (WAL-safe)..."
SQLITE_OK=1

backup_db() {
  local db="$1" src="${DATA_ROOT}/${1}.db" dst="${BACKUP_DIR}/${1}.db" rc=1

  # Prioritas 1: sqlite3 CLI (.backup)
  if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 --safe "file:${src}?mode=ro" ".backup '${dst}'" >/dev/null 2>&1 && rc=0
  fi

  # Prioritas 2: python3 sqlite3.backup (sama konsisten, tanpa CLI)
  if [ "$rc" != "0" ] && command -v python3 >/dev/null 2>&1; then
    python3 -c '
import sqlite3, sys
src, dst = sys.argv[1], sys.argv[2]
con = sqlite3.connect("file:%s?mode=ro" % src, uri=True)
out = sqlite3.connect(dst)
con.backup(out)
out.close(); con.close()
' "$src" "$dst" >/dev/null 2>&1 && rc=0
  fi

  if [ "$rc" = "0" ]; then
    log "  OK   ${db}.db"
  else
    # Prioritas 3: copy statis (tidak konsisten jika ada tulis aktif — terakhir resort).
    cp -p "$src" "$dst" 2>/dev/null && rc=0
    if [ "$rc" = "0" ]; then
      log "  WARN ${db}.db di-backup via cp statis (periksa konsistensi)"
    else
      log "  WARN ${db}.db GAGAL di-backup"; SQLITE_OK=0
    fi
  fi
}

for db in state projects kanban response_store verification_evidence; do
  if [ -f "${DATA_ROOT}/${db}.db" ]; then
    backup_db "$db"
  else
    log "  SKIP ${db}.db (tidak ada)"
  fi
done

# --- 2) Archive file & direktori lain (KECUALI .env & runtime temporer) -----
log "Membuat archive tar.gz..."
BACKUP_LIST=()
for item in "${BACKUP_ITEMS[@]}"; do
  [ -e "${DATA_ROOT}/${item}" ] && BACKUP_LIST+=("$item")
done

if [ "${#BACKUP_LIST[@]}" -gt 0 ]; then
  # --exclude untuk artefak yang tidak perlu di-backup (runtime/sementara).
  tar czf "${BACKUP_DIR}/data.tar.gz" \
    --exclude='*.pid' --exclude='*.sock' --exclude='*.lock' --exclude='*.tmp' \
    --exclude='models_dev_cache*' --exclude='.models*' --exclude='__pycache__' \
    --exclude='.git' \
    -C "$DATA_ROOT" "${BACKUP_LIST[@]}"
  log "  OK   data.tar.gz ($(du -h "${BACKUP_DIR}/data.tar.gz" | cut -f1))"
else
  log "  WARN tidak ada item untuk di-archive."
fi

# --- 3) Verifikasi ----------------------------------------------------------
OK=1
[ -f "${BACKUP_DIR}/state.db" ] && [ -s "${BACKUP_DIR}/state.db" ] || OK=0
[ -f "${BACKUP_DIR}/data.tar.gz" ] && [ -s "${BACKUP_DIR}/data.tar.gz" ] || OK=0

if [ "$OK" = "1" ] && command -v sqlite3 >/dev/null 2>&1; then
  log "Verifikasi integrity database..."
  for db in state projects kanban response_store verification_evidence; do
    if [ -f "${BACKUP_DIR}/${db}.db" ]; then
      if sqlite3 --safe "file:${BACKUP_DIR}/${db}.db?mode=ro" "PRAGMA integrity_check;" 2>/dev/null | grep -q "^ok$"; then
        log "  OK integrity ${db}.db"
      else
        log "  FAIL integrity ${db}.db"
        OK=0
      fi
    fi
  done
fi

# --- 4) Manifest ------------------------------------------------------------
cat > "${BACKUP_DIR}/MANIFEST.txt" <<EOF
Hermes backup
=============
Tanggal      : $(date '+%Y-%m-%d %H:%M:%S %Z')
Host         : $(hostname)
Arsitektur   : container / s6 / gateway $(awk -F'"' '/code_version/{print $2}' /opt/data/gateway_state.json 2>/dev/null || echo n/a)
Sumber       : $DATA_ROOT
Isi          : ${BACKUP_LIST[*]:-none}
Ukuran total : $(du -sh "$BACKUP_DIR" | cut -f1)
EOF
log "  OK   MANIFEST.txt"

# --- 5) Rotasi --------------------------------------------------------------
log "Rotasi: mempertahankan $KEEP backup terakhir..."
cd "$BACKUP_ROOT"
ls -1dt hermes-* 2>/dev/null | tail -n +$((KEEP + 1)) | while read -r old; do
  rm -rf "$BACKUP_ROOT/$old"
  log "  Hapus backup lama: $old"
done

# --- 6) Ringkasan -----------------------------------------------------------
log "== Selesai =="
if [ "$OK" = "1" ]; then
  log "✓ Backup BERHASIL: $BACKUP_DIR ($(du -sh "$BACKUP_DIR" | cut -f1))"
  exit 0
else
  log "✗ Backup GAGAL verifikasi. Periksa log: $LOG_FILE"
  exit 1
fi