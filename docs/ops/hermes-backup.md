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

### Desain keamanan backup (dua tahap)

Backup dibagi menjadi **2 archive** dengan tingkat keamanan berbeda:

| Archive | Isi | Perlakuan |
|---|---|---|
| `data.tar.gz` | memori, skills, plugins, logs, cron, gateway state, repo proyek | **Aman / plaintext** — tidak boleh berisi credential. Divervikasi secret-leak scan otomatis. |
| `secrets.tar.gz.enc` | `config.yaml*`, `sessions/`, semua `*.db`, `.env*` | **Terenkripsi AES-256** (openssl + pbkdf2 200.000 iterasi). Hanya dibuat jika `ENCRYPTION_PASSWORD` diset. |

**Prinsip:** credential (API key, token Discord, riwayat request yang memuat auth) **tidak pernah**
berada dalam archive plaintext. Jika tidak ada password, maka item rahasia **tidak ikut di-backup**
(skrip memberi peringatan jelas), bukan malah ikut dalam bentuk mentah.

Ada **secret-leak scan otomatis**: setelah archive aman dibuat, skrip membongkar lalu memindainya
dengan detektor presisi (pola `sk-*`, `ghp_*`, `AKIA*`, private key, `Authorization:`, `api_key=`,
`x-*-key`). Jika ada jejak credential, backup **DITOLAK** (exit non-zero) — sehingga mustahil
backup yang bocor lolos tanpa disadari.

## 2. Cara memakai skrip

### A. Dari dalam container — backup aman (tanpa rahasia)

```bash
bash /opt/data/school-finance-system/scripts/hermes-backup.sh
# → data.tar.gz (41M) + NOTE: archive rahasia tidak ikut
```

### B. Dari dalam container — backup LENGKAP (dengan enkripsi)

```bash
# Wajib: beri password enkripsi — via env var (disarankan):
ENCRYPTION_PASSWORD='password-kuat-jangan-hilang' \
  bash /opt/data/school-finance-system/scripts/hermes-backup.sh

# Atau via file (aman untuk cron, file ber-mode 600):
echo -n 'password-kuat-jangan-hilang' > /opt/data/.backup-pass
chmod 600 /opt/data/.backup-pass
ENCRYPTION_PASSWORD_FILE=/opt/data/.backup-pass \
  bash /opt/data/school-finance-system/scripts/hermes-backup.sh
```

Variabel opsional lain: `BACKUP_ROOT`, `KEEP` (default 7), `LOG_TO_FILE=1`.

### C. Otomatis via cron (disarankan — mode lengkap)

```bash
# crontab VPS — setiap hari 03:00 WIB
0 3 * * * /usr/bin/docker exec -e ENCRYPTION_PASSWORD_FILE=/opt/data/.backup-pass \
  hermes bash /opt/data/school-finance-system/scripts/hermes-backup.sh >> /var/log/hermes-backup.log 2>&1
```

> Simpan password di file ber-mode `600`, jangan hardcode di crontab baris perintah
> (bisa terlihat lewat `ps`). Jika password hilang, archive rahasia TIDAK bisa dibuka —
> simpan salinannya di tempat aman terpisah (password manager).

## 3. Restore

### Periksa isi backup

```bash
tar tzf /opt/data/_backups/hermes-<timestamp>/data.tar.gz | head
# archive rahasia harus di-decrypt dulu:
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -salt \
  -pass 'pass:password-kuat-jangan-hilang' \
  -in  /opt/data/_backups/hermes-<timestamp>/secrets.tar.gz.enc \
  -out /tmp/secrets.tar.gz
tar tzf /tmp/secrets.tar.gz
```

### Restore nyata (saat gateway BERHENTI)

```bash
# 1. Stop gateway dulu (hindari konflik tulis):
#    hermes gateway stop   (atau touch /opt/data/gateway.lock + kill PID)

# 2. Extraksi & salin balik (dari folder backup terbaru):
B=/opt/data/_backups/hermes-<timestamp>
tar xzf "$B/data.tar.gz" -C /opt/data
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -salt \
  -pass 'pass:password-kuat-jangan-hilang' \
  -in "$B/secrets.tar.gz.enc" -out /tmp/secrets.tar.gz
tar xzf /tmp/secrets.tar.gz -C /opt/data

# 3. Jalankan ulang gateway:
#    hermes gateway run   (atau biarkan s6 yang me-restart)
```

## 4. Keamanan & checklist

- `.env`, `config.yaml`, DB, dan sessions **tidak pernah** dalam plaintext backup.
- Folder `skills/` berisi dokumentasi contoh sintaks (`***`, `$VAR`) — bukan rahasia runtime,
  sehingga dikecualikan dari secret-leak scan konten (tetap di-backup).
- [ ] Cek berkala log backup (`/var/log/hermes-backup.log` atau `/opt/data/_backups/backup.log`)
- [ ] Pastikan minimal 1 backup lengkap (dengan `.enc`) berhasil per minggu
- [ ] Simulasikan restore minimal sekali (lihat §3) — terutama decrypt archive rahasia
- [ ] Salinan password enkripsi di tempat terpisah dari VPS
- [ ] Duplikasikan backup ke lokasi di luar VPS (object storage / VPS lain)