# Panduan Backup Hermes di VPS

> Terkait: [Diagram arsitektur](../architecture/hermes-server-architecture.md) · [Skrip backup](../../scripts/hermes-backup.sh)
> Repo: https://github.com/fayzisme/keuangan-sekolah

## 1. Ringkasan

Semua data penting Hermes yang **persisten** berada di **disk VPS** (bukan di dalam image
container, yang volatil):

| Mount (dalam container) | Lokasi fisik di VPS | Isi |
|---|---|---|
| `/opt/data` (`HERMES_HOME`) | subfolder/bind mount pada `/dev/vda2` | config, DB SQLite, sesi, memori, skills, logs, repo proyek |
| `/app/workspace` | subfolder terpisah pada `/dev/vda2` | direktori kerja agent (salinan docs lama) |

**Hal penting:** DB Hermes memakai SQLite **WAL** dan gateway **selalu menulis** (24/7).
Backup naif `cp` bisa menghasilkan file yang tidak konsisten. Skrip kami memakai
`sqlite3 .backup` yang aman walau database sedang aktif → **zero downtime**.

## 2. Cara memakai skrip

### A. Dari dalam container (paling mudah — langsung jalan)

```bash
# Jalankan default: simpan ke /opt/data/_backups/hermes-<timestamp>/
bash /opt/data/school-finance-system/scripts/hermes-backup.sh

# Simpan ke lokasi lain (misal mount NFS/ext4 eksternal) + tulis log
BACKUP_ROOT=/mnt/backup/hermes KEEP=14 LOG_TO_FILE=1 \
  bash /opt/data/school-finance-system/scripts/hermes-backup.sh
```

### B. Dari host VPS (tanpa masuk container)

```bash
# Asumsikan nama containernya hermes; cek dulu: sudo docker ps / ctr -n ... c ls
sudo docker exec <container> bash /opt/data/school-finance-system/scripts/hermes-backup.sh

# Atau backup langsung dari host (data = bind mount /opt/data di VPS):
#   → jalankan skrip di dalam container agar SQLite .backup aman dipakai.
```

### C. Otomatis via cron (disarankan)

Tambahkan baris berikut ke crontab VPS (`crontab -e`) — backup tiap hari 03:00 WIB:

```
0 3 * * * /usr/bin/docker exec hermes bash /opt/data/school-finance-system/scripts/hermes-backup.sh >> /var/log/hermes-backup.log 2>&1
```

> Atau dari dalam container pakai cron Hermes (job JSON di `/opt/data/cron/jobs.json`).

## 3. Keamanan & enkripsi

- **`.env` TIDAK ikut ter-archive** oleh skrip (di-exclude) karena berisi token Discord
  dan API keys. Salinan `.env.bak` yang sudah ada di `/opt/data` tetap tersedia secara lokal.
- Jika kamu **ingin** cadangan credential ikut ter-backup, **enkripsi dulu**:

```bash
# 1. tar data sensitif
tar czf secrets.tar.gz -C /opt/data .env

# 2. enkripsi dengan age (disarankan) — simpan key di tempat aman terpisah
age -r age1... -o secrets.tar.gz.age secrets.tar.gz
# atau gpg
gpg --symmetric --cipher-algo AES256 secrets.tar.gz
```

- Untuk backup ke lokasi remote (VPS lain / object storage), upload + setelah upload
  hapus salinan lokal, atau gunakan `restic` / `rclone` dengan enkripsi bawaan.

## 4. Verifikasi & restore

### Cek isi backup

```bash
ls -lh /opt/data/_backups/hermes-<timestamp>/
tar tzf /opt/data/_backups/hermes-<timestamp>/data.tar.gz | head
```

Skrip sudah otomatis menjalankan `PRAGMA integrity_check` pada semua DB yang di-backup.

### Simulasi restore (ke folder kosong)

```bash
mkdir -p /tmp/restore-test
tar xzf /opt/data/_backups/hermes-<timestamp>/data.tar.gz -C /tmp/restore-test
# cek DB
sqlite3 /tmp/restore-test/state.db "PRAGMA integrity_check;"
```

### Restore nyata (hanya saat diperlukan)

> ⚠️ Lakukan saat gateway **berhenti** untuk menghindari konflik tulis.
> Simpan dulu data lama (jangan langsung ditimpa).

```bash
# 1. Stop gateway (dalam container)
hermes gateway stop          # atau: touch /opt/data/gateway.lock + kill PID

# 2. Salin balik DB & data
cp /opt/data/_backups/hermes-<timestamp>/state.db /opt/data/state.db
cp /opt/data/_backups/hermes-<timestamp>/projects.db /opt/data/projects.db
# ...dst (sesuai item di backup)

# 3. Extract archive lain
tar xzf /opt/data/_backups/hermes-<timestamp>/data.tar.gz -C /opt/data

# 4. Jalankan ulang gateway
hermes gateway run           # atau biarkan s6 yang me-restart
```

## 5. Checklist operasional

- [ ] Backup otomatis harian berjalan (cek `/var/log/hermes-backup.log` atau `_backups/`)
- [ ] Setidaknya 1 backup berhasil diverifikasi (integrity check `ok`)
- [ ] Restore pernah disimulasikan minimal sekali (lihat §4)
- [ ] Key enkripsi disimpan terpisah dari server (jika gunakan enkripsi)
- [ ] Backup dipindahkan/duplikat ke lokasi di luar VPS yang sama (bencana disk VPS)