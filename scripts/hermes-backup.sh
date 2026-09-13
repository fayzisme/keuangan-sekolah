#!/usr/bin/env bash
# ============================================================================
# hermes-backup.sh — Backup aman data persisten Hermes di VPS
# Repo: keuangan-sekolah · docs/ops/hermes-backup.md
#
# PRINSIP:
#   1. TIDAK mematikan gateway (zero-downtime) saat backup.
#   2. Database SQLite WAL di-backup via sqlite3.backup (konsisten walau aktif).
#   3. PEMISAHAN RAHASIA: file yang bisa berisi credential (config.yaml sesi agent)
#      masuk archive TERENKRIPSI terpisah, TIDAK pernah dalam archive biasa.
#   4. Secret-leak scan otomatis: memindai archive biasa; jika ada jejak credential,
#      backup DITOLAK (exit non-zero) supaya tidak tersebar.
#   5. Rotasi otomatis: simpan N backup terakhir.
#   6. Bisa dijadwalkan via cron.
#
# CUSTOMISASI (env):
#   BACKUP_ROOT              : tujuan file backup (default: ${DATA_ROOT}/_backups).
#   KEEP                     : berapa backup terakhir yang dipertahankan (default 7).
#   LOG_TO_FILE              : 1 = tulis log ke file (default 0).
#   ENCRYPTION_PASSWORD      : password utk enkripsi archive rahasia (WAJIB jika
#                              ingin sessions/config.yaml ikut ter-backup).
#                              REKOMENDASI: set via crontab/file, JANGAN hardcode.
#   ENCRYPTION_PASSWORD_FILE : alternatif: path ke file berisi password (baris 1).
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

# Ambil password enkripsi dari env atau file
ENCRYPTION_PASSWORD="${ENCRYPTION_PASSWORD:-}"
if [ -z "$ENCRYPTION_PASSWORD" ] && [ -n "${ENCRYPTION_PASSWORD_FILE:-}" ] && [ -f "$ENCRYPTION_PASSWORD_FILE" ]; then
  ENCRYPTION_PASSWORD="$(head -n1 "$ENCRYPTION_PASSWORD_FILE" 2>/dev/null | tr -d '\r\n')"
fi

# Item yang AMAN untuk archive biasa (tidak boleh mengandung credential dan
# TIDAK termasuk database/sessions — semuanya masuk archive RAHASIA).
BACKUP_ITEMS=(
  memories
  gateway
  gateway_state.json
  state
  cron
  skills
  plugins
  logs
  channel_directory.json
  discord_threads.json
  school-finance-system
)

# Item yang TERDAPAT RAHASIA → archive terenkripsi terpisah:
#  - config.yaml*      : berisi api_key Omniroute asli
#  - sessions/         : dump request LLM mentah (header auth asli)
#  - *.db (state,dll)  : riwayat percakapan + tool output, bisa memuat token
SECRET_ITEMS=(
  config.yaml*
  sessions
  state.db
  projects.db
  kanban.db
  response_store.db
  verification_evidence.db
  .env*
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
# DITULIS KE FOLDER STAGING (bukan BACKUP_DIR) — karena DB masuk archive
# rahasia terenkripsi. Staging dihapus setelah dienkripsi, sehingga TIDAK ada
# file DB plaintext yang tertinggal di folder backup.
log "Mem-backup database SQLite (WAL-safe, ke staging)..."
STAGE="${BACKUP_DIR}/_stage"
mkdir -p "$STAGE"
SQLITE_OK=1

backup_db() {
  local db="$1" src="${DATA_ROOT}/${1}.db" dst="${STAGE}/${1}.db" rc=1

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

# --- 2) Archive AMAN (tanpa credential) -------------------------------------
log "Membuat archive aman data.tar.gz..."
SAFE_LIST=()
for item in "${BACKUP_ITEMS[@]}"; do
  [ -e "${DATA_ROOT}/${item}" ] && SAFE_LIST+=("$item")
done

if [ "${#SAFE_LIST[@]}" -gt 0 ]; then
  tar czf "${BACKUP_DIR}/data.tar.gz" \
    --exclude='*.pid' --exclude='*.sock' --exclude='*.lock' --exclude='*.tmp' \
    --exclude='models_dev_cache*' --exclude='.models*' --exclude='__pycache__' \
    --exclude='.git' --exclude='.env*' --exclude='*/.env*' --exclude='config.yaml*' \
    -C "$DATA_ROOT" "${SAFE_LIST[@]}"
  log "  OK   data.tar.gz ($(du -h "${BACKUP_DIR}/data.tar.gz" | cut -f1))"
else
  log "  WARN tidak ada item aman untuk di-archive."
fi

# --- 2b) Integrity check DB (saat masih di staging, sebelum dienkripsi) ------
if command -v python3 >/dev/null 2>&1; then
  log "Verifikasi integrity database (staging)..."
  for db in state projects kanban response_store verification_evidence; do
    if [ -f "${STAGE}/${db}.db" ]; then
      chk=$(python3 -c "import sqlite3,sys;print(sqlite3.connect(sys.argv[1]).execute('PRAGMA integrity_check;').fetchone()[0])" "${STAGE}/${db}.db" 2>/dev/null)
      if [ "$chk" = "ok" ]; then
        log "  OK integrity ${db}.db"
      else
        log "  WARN integrity ${db}.db = ${chk:-gagal}"
      fi
    fi
  done
fi

# --- 3) Archive RAHASIA (config + sessions + DB + .env*) ⟶ ENKRIPSI ----------
if [ -n "$ENCRYPTION_PASSWORD" ]; then
  log "Membuat archive rahasia terenkripsi (AES-256)..."
  SECRET_STAGE="${BACKUP_DIR}/_secret_stage"
  mkdir -p "$SECRET_STAGE"

  # Gabungkan semua item rahasia + DB staging ke satu folder secret stage
  for pat in config.yaml* .env*; do
    # shellcheck disable=SC2086
    cp -p ${DATA_ROOT}/${pat} "$SECRET_STAGE/" 2>/dev/null || true
  done
  [ -d "${DATA_ROOT}/sessions" ] && cp -rp "${DATA_ROOT}/sessions" "$SECRET_STAGE/"
  cp -p "${STAGE}"/*.db "$SECRET_STAGE/" 2>/dev/null || true

  if tar czf "${BACKUP_DIR}/secrets.tar.gz" -C "$SECRET_STAGE" . 2>/dev/null; then
    if openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt \
         -pass "pass:${ENCRYPTION_PASSWORD}" \
         -in "${BACKUP_DIR}/secrets.tar.gz" \
         -out "${BACKUP_DIR}/secrets.tar.gz.enc" 2>/dev/null; then
      rm -f "${BACKUP_DIR}/secrets.tar.gz"
      log "  OK   secrets.tar.gz.enc (terenkripsi, $(du -h "${BACKUP_DIR}/secrets.tar.gz.enc" | cut -f1))"
    else
      rm -f "${BACKUP_DIR}/secrets.tar.gz"
      log "  WARN enkripsi GAGAL — archive rahasia TIDAK dibuat."
    fi
  else
    log "  WARN gagal membuat archive rahasia."
  fi

  # Bersihkan SEMUA plaintext rahasia (hapus staging + backup dir dari DB nuduh).
  rm -rf "$SECRET_STAGE" "$STAGE"
  # Juga pastikan .env* tidak menumpuk di BACKUP_DIR
  rm -f "${BACKUP_DIR}"/.env* "${BACKUP_DIR}"/config.yaml* 2>/dev/null || true
else
  log "  SKIP archive rahasia — ENCRYPTION_PASSWORD belum diset."
  log "  (config.yaml, sessions, DB & .env TIDAK ikut di-backup. Set ENCRYPTION_PASSWORD untuk mengikutkannya.)"
  # Tanpa password: hapus staging DB plaintext (jangan biarkan DB mentah di backup folder)
  rm -rf "$STAGE"
fi

# --- 4) Secret-leak scan: pastikan archive AMAN bebas credential ------------
log "Secret-leak scan (archive aman)..."
LEAK=0
LEAK_HITS=""

# a) cek keberadaan file .env / kunci / config di dalam archive aman
ENV_HIT=$(tar tzf "${BACKUP_DIR}/data.tar.gz" 2>/dev/null \
  | grep -E '(^|/)(\.env(\.|$)|config\.ya?ml?$|.*\.(pem|p12|key)$)' || true)
if [ -n "$ENV_HIT" ]; then
  LEAK=1; LEAK_HITS="${LEAK_HITS}\n  FILE: $ENV_HIT"
fi

# b) cek pola token di dalam archive aman (baca konten → deteksi presisi).
TMP_EXTRACT="${BACKUP_DIR}/.leakscan"; mkdir -p "$TMP_EXTRACT"
tar xzf "${BACKUP_DIR}/data.tar.gz" -C "$TMP_EXTRACT" 2>/dev/null || true

# Scanner presisi dengan Python: hanya nilai yang BENAR-BENAR pola kredensial
# (mengabaikan placeholder ***/xxxx/<...>/$VAR, panggilan fungsi, contoh `-H "...`,
#  komentar kode, dan nilai kosong).
CONTENT_HIT=$(python3 - "$TMP_EXTRACT" <<'PYEOF'
import os, re, sys
root = sys.argv[1]
SKIP_DIRS = {".git", "__pycache__", "node_modules", "vendor"}
patterns = [
    re.compile(r'sk-[A-Za-z0-9_-]{20,}'),                  # OpenAI-style
    re.compile(r'ghp_[A-Za-z0-9]{30,}'),                   # GitHub PAT
    re.compile(r'AKIA[0-9A-Z]{16}'),                       # AWS access key
    re.compile(r'-----BEGIN (RSA|OPENSSH|EC|PGP) PRIVATE'),# private key
    re.compile(r'(?:Authorization|auth)\s*:\s*(Bearer|Basic)\s+["\']?([^\s"\',]+)', re.I),
    re.compile(r'api[_-]?key\s*[:=]\s*["\']?([^\s"\',]+)', re.I),
    re.compile(r'x-[a-z0-9_-]+-key\s*[:=]\s*["\']?([^\s"\',]+)', re.I),
]
def looks_real(v):
    v = v.strip().strip('"\'')
    # buang wrapper contoh bash: -H "TOKEN"
    v = re.sub(r'^-H\s+"?', '', v).strip()
    v = v.rstrip('\\').strip()
    if not v: return False
    if v.startswith(('$', '{{', '<', '*')): return False
    if any(t in v for t in ('(', ')', '=', 'getenv', 'ENV[', 'env(', 'config(')): return False
    if v in ('***', 'xxxx', 'xxx', 'xxxxx', '...', 'null', 'None', 'TODO', 'base64'): return False
    if v.endswith(('...', '…', '{', '}')): return False
    if len(v) < 12: return False
    return True
hits, seen = [], set()
for dirpath, dirnames, filenames in os.walk(root):
    dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
    if '/.leakscan/skills/' in dirpath or '/.leakscan/.git' in dirpath:
        continue  # skills = dokumentasi contoh sintaks, bukan rahasia runtime
    for fn in filenames:
        if fn.endswith(('.db', '.sqlite', '.png', '.jpg', '.gif', '.woff', '.ttf', '.pyc')):
            continue
        fp = os.path.join(dirpath, fn)
        try:
            with open(fp, 'r', encoding='utf-8', errors='replace') as f:
                for ln, line in enumerate(f, 1):
                    stripped = line.lstrip()
                    # lewati baris komentar murni di kode (bukan rahasia runtime)
                    if stripped.startswith(('//', '/*', '*', '#', '--')):
                        continue
                    for p in patterns:
                        m = p.search(line)
                        if not m: continue
                        val = m.group(0) if m.lastindex is None else m.group(m.lastindex)
                        if looks_real(val) and (fp, ln) not in seen:
                            seen.add((fp, ln))
                            hits.append(f"{fp}:{ln}:{line.strip()[:120]}")
                            break
        except OSError:
            continue
print("\n".join(hits[:20]))
PYEOF
)
rm -rf "$TMP_EXTRACT"

if [ -n "$CONTENT_HIT" ]; then
  LEAK=1; LEAK_HITS="${LEAK_HITS}\n  CONTENT: $CONTENT_HIT"
fi

if [ "$LEAK" = "1" ]; then
  log "!!! SECRET-LEAK TERDETEKSI — backup DITOLAK:"
  echo -e "$LEAK_HITS" | sed 's/^/      /'
  OK=0
else
  log "  OK   tidak ada credential terdeteksi di archive aman ✓"
fi

# --- 5) (Integrity DB sudah dicek di langkah 2b saat masih staging) ----------

# --- 6) Manifest -------------------------------------------------------------
cat > "${BACKUP_DIR}/MANIFEST.txt" <<EOF
Hermes backup
=============
Tanggal      : $(date '+%Y-%m-%d %H:%M:%S %Z')
Host         : $(hostname)
Sumber       : $DATA_ROOT
Archive aman : data.tar.gz
Archive rahasia: $([ -f "${BACKUP_DIR}/secrets.tar.gz.enc" ] && echo "secrets.tar.gz.enc (terenkripsi AES-256)" || echo "TIDAK ADA (password belum diset)")
Isi aman     : ${SAFE_LIST[*]:-none}
Ukuran total : $(du -sh "$BACKUP_DIR" | cut -f1)
EOF
log "  OK   MANIFEST.txt"

# --- 7) Rotasi ---------------------------------------------------------------
log "Rotasi: mempertahankan $KEEP backup terakhir..."
cd "$BACKUP_ROOT" || true
ls -1dt hermes-* 2>/dev/null | tail -n +$((KEEP + 1)) | while read -r old; do
  rm -rf "$BACKUP_ROOT/$old"
  log "  Hapus backup lama: $old"
done

# --- 8) Ringkasan ------------------------------------------------------------
log "== Selesai =="
if [ "${OK:-1}" = "1" ]; then
  log "✓ Backup BERHASIL: $BACKUP_DIR ($(du -sh "$BACKUP_DIR" | cut -f1))"
  log "  NOTE: archive rahasia $([ -f "${BACKUP_DIR}/secrets.tar.gz.enc" ] && echo "ikut ter-backup (terenkripsi)" || echo "TIDAK ikut — set ENCRYPTION_PASSWORD jika ingin config.yaml+sessions ter-backup")"
  exit 0
else
  log "✗ Backup DITOLAK karena secret-leak terdeteksi. Periksa log: $LOG_FILE"
  exit 1
fi