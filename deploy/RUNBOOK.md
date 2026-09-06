# RUNBOOK — Deploy Manual VPS

> **Versi:** 1.0 · **Target:** VPS `43.173.7.25` · Ubuntu 24.04 LTS
> **Repo:** https://github.com/fayzisme/keuangan-sekolah (public, branch `main`)
> **Alur:** bootstrap server → clone → build frontend → `.env` → compose up → healthz → pilot sekolah → HTTPS → backup cron → monitoring
>
> Jalur ini untuk **deploy manual** (opsi runbook). Alternatif CI: workflow `.github/workflows/ci.yml` job `deploy` memakai GitHub Secrets `PROD_HOST`, `PROD_USER`, `PROD_SSH_KEY`, `PROD_DOMAIN` (belum dikonfigurasi — opsional).

---

## 0. Prasyarat

1. **Domain + DNS:** buat A record `<DOMAIN>` → `43.173.7.25` (wajib sebelum langkah HTTPS, §7).
2. **SSH key laptop:** `ssh-keygen -t ed25519` (bila belum punya); public key siap di-copy.
3. **Akses root VPS** via console/panel provider (jaring pengaman bila SSH hardening salah).

---

## 1. Bootstrap server — root (sekali saja)

```bash
ssh root@43.173.7.25

# --- 1a. User deploy + SSH key laptop ---
adduser deploy && usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
echo 'ssh-ed25519 AAAA... <komentar-laptop>' > /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys

# --- 1b. Grup docker (deploy harus bisa `docker compose` polos) ---
usermod -aG docker deploy

# --- 1c. Jalankan bootstrap (idempotent, aman diulang) ---
# UFW 22/80/443 + fail2ban + unattended-upgrades + Docker + SSH hardening key-only
curl -fsSL https://raw.githubusercontent.com/fayzisme/keuangan-sekolah/main/deploy/bootstrap.sh -o /tmp/bootstrap.sh
chmod +x /tmp/bootstrap.sh
sudo bash /tmp/bootstrap.sh
```

> ⚠️ `bootstrap.sh` meng-hardening SSH **hanya bila** `/home/deploy/.ssh/authorized_keys` tidak kosong (guard built-in). Test dari laptop dulu:
> ```bash
> ssh deploy@43.173.7.25
> ```

---

## 2. Clone repo + build frontend (user deploy)

```bash
ssh deploy@43.173.7.25
sudo apt update && sudo apt install -y git jq   # jq opsional (pretty JSON)

sudo mkdir -p /srv/school-finance && sudo chown deploy:deploy /srv/school-finance
git clone https://github.com/fayzisme/keuangan-sekolah.git /srv/school-finance
cd /srv/school-finance
```

`web/dist` di-gitignore → wajib di-build (nginx me-mount `./web/dist`):

**Opsi B — build di server (tanpa node di host):**
```bash
mkdir -p /srv/school-finance/web/dist
docker run --rm -v /srv/school-finance:/app -w /app/web node:20-alpine \
  sh -c 'npm ci && npm run build && ls dist/index.html'
```

**Opsi A — build di laptop, lalu scp:**
```bash
# di laptop: cd school-finance && npm ci && npm run build
scp -r web/dist deploy@43.173.7.25:/srv/school-finance/web/
```

---

## 3. `.env` — secret produksi (dibuat hanya di server, tidak pernah masuk git)

```bash
cd /srv/school-finance
cp .env.example .env
nano .env
```

Nilai yang di-generate:

| Variabel | Perintah | Catatan |
|---|---|---|
| `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"` | AES-256 valid (32 byte) |
| `DB_PASSWORD` | `openssl rand -base64 24` | kuat, min 16 karakter acak |
| `REDIS_PASSWORD` | `openssl rand -hex 16` | |
| `PLATFORM_KEY` | `openssl rand -hex 32` | dipakai header `X-Platform-Key` saat onboarding |
| `APP_URL` | `https://<DOMAIN>` | |
| `FRONTEND_URL` | `https://<DOMAIN>` | |
| `SANCTUM_STATEFUL_DOMAINS` | `<DOMAIN>` | |

Akhiri dengan: `chmod 600 .env`

---

## 4. Build image + up stack

```bash
cd /srv/school-finance
docker compose build app                         # build image (composer install dalam stage vendor)
docker compose up -d postgres redis              # DB/Redis lebih dulu
docker compose run --rm app php artisan migrate --force   # migrate SEBELUM app up
docker compose up -d                             # nginx + app + worker + scheduler
docker compose ps                                # semua service Up (healthy)
docker compose logs --tail=50 app
```

> Image di-tag lokal `ghcr.io/owner/repo-app:latest` (var `GITHUB_REPOSITORY` default). Rollback via pin tag git + rebuild, lihat §9.

---

## 5. Health check + smoke test (HTTP)

```bash
curl -s http://localhost/healthz | jq .          # {"status":"ok","database":"ok","cache":"ok",...}
curl -s http://localhost/api/v1/ping | jq .      # {"status":"ok","message":"School Finance API v1 siap."}
```

---

## 6. Pilot sekolah pertama (onboarding platform)

```bash
cd /srv/school-finance
source .env
curl -s -X POST http://localhost/api/v1/platform/schools \
  -H "Content-Type: application/json" \
  -H "X-Platform-Key: $PLATFORM_KEY" \
  -d '{
    "name": "SMA Pilot",
    "admin_name": "Admin Pilot",
    "admin_email": "admin@pilot.sch.id",
    "admin_password": "S3cur3-Passw0rd!"
  }' | jq .
# → HTTP 201: { school:{id,name}, admin:{id,name,email} }
#   (role:admin di-team sekolah dibuat otomatis dalam satu transaksi)

# Login + cek /me (Sanctum bearer)
TOKEN=$(curl -s -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@pilot.sch.id","password":"S3cur3-Passw0rd!"}' | jq -r .token)
curl -s http://localhost/api/v1/auth/me -H "Authorization: Bearer $TOKEN" | jq .
```

Alur uji fungsional: login → master data (academic year, kelas, siswa) → bill-types → generate invoice → pembayaran manual + verifikasi → laporan → export PDF/Excel.

---

## 7. HTTPS — Let's Encrypt

Prasyarat: DNS A record sudah menunjuk ke `43.173.7.25`.

```bash
cd /srv/school-finance
sudo apt install -y certbot
mkdir -p web/dist/.well-known                    # webroot challenge
sudo certbot certonly --webroot -w /srv/school-finance/web/dist \
  -d <DOMAIN> --agree-tos --no-eff-email -m admin@<DOMAIN>
```

Aktifkan konfig TLS (template sudah berisi CSP, HSTS, TLS 1.2+):

```bash
sed 's/<DOMAIN>/<DOMAIN>/g' deploy/nginx/https.conf.example > deploy/nginx/default.conf
docker compose restart nginx
curl -fsS https://<DOMAIN>/healthz | jq .                       # OK
curl -sI https://<DOMAIN> | grep -i strict-transport-security    # HSTS aktif
```

> HSTS `max-age=1y` sudah aktif di template — hanya setelah HTTPS terbukti stabil (jangan dimatikan).

---

## 8. Backup harian (cron; 02:00 WIB = 19:00 UTC)

```bash
crontab -e    # sebagai user deploy
```
```cron
0 19 * * * cd /srv/school-finance && ./deploy/backup.sh >> /var/log/school-finance-backup.log 2>&1
```

Uji segera:
```bash
cd /srv/school-finance && ./deploy/backup.sh && ls -lh /var/backups/school-finance/
```

**Uji restore bulanan (wajib):**
```bash
gzip -dk /var/backups/school-finance/school-<stempel-waktu>.sql.gz
docker compose exec -T -e PGPASSWORD="$DB_PASSWORD" postgres \
  psql -U "$DB_USERNAME" -d "$DB_DATABASE" < /var/backups/school-finance/school-<stempel-waktu>.sql
# bandingkan jumlah baris tabel kunci dengan produksi
```

> Enkripsi backup: set `GPG_KEY_ID` di `.env`, lalu nyalakan blok `gpg` di `deploy/backup.sh`.

---

## 9. Update & rollback

```bash
# Update (urutan aman)
cd /srv/school-finance
git pull
docker run --rm -v /srv/school-finance:/app -w /app/web node:20-alpine sh -c 'npm ci && npm run build'
./deploy/backup.sh                               # backup SEBELUM migrate
docker compose run --rm app php artisan migrate --force
docker compose up -d --build
curl -fsS https://<DOMAIN>/healthz | jq .
```

```bash
# Rollback (image build lokal): pin source ke tag sebelumnya
git fetch --tags && git checkout <tag-sebelumnya>
docker compose up -d --build
```

> Jalur CI alternatif: set `APP_IMAGE_TAG=sha-<short>` + `GITHUB_REPOSITORY=fayzisme/keuangan-sekolah` di `.env` → `docker compose pull app worker scheduler` memakai image CI dari GHCR (immutable; rollback cukup ganti tag lalu `up -d`).

---

## 10. Monitoring & operasional

| Keperluan | Perintah / Alat |
|---|---|
| Health | `curl -s https://<DOMAIN>/healthz` → Uptime Kuma / UptimeRobot (interval 5 menit) |
| Log aplikasi | `docker compose logs -f --tail=100 app` |
| Worker queue | `docker compose logs --tail=50 worker` |
| Scheduler | `docker compose logs --tail=50 scheduler` |
| Disk & sertifikat | `df -h` · Uptime Kuma (cert expiry) |
| Retry job gagal | `docker compose run --rm app php artisan queue:retry all` |

---

## 11. Checklist keamanan (final)

- [ ] UFW hanya 22/80/443 — cek `sudo ufw status`
- [ ] SSH key-only, root login mati — `ssh deploy@43.173.7.25` dari laptop
- [ ] fail2ban aktif — `sudo fail2ban-client status sshd`
- [ ] `.env` permission `600`, tidak ada di git
- [ ] DB/Redis internal Docker network (tanpa port host) — `docker compose ps` tidak memuat `0.0.0.0:5432`
- [ ] Backup harian jalan + uji restore sudah dilakukan
- [ ] Health monitor aktif
- [ ] (Fast-follow, setelahnya) Midtrans + notifikasi WA: isi `MIDTRANS_*`/`TELEGRAM_*` di `.env` — lihat ADR-0015/0016

---

## 12. Catatan versi deploy ini (commit `59334ba`)

- CI `backend` (composer→pint→migrate→pest 251 assertions), `frontend`, `build-image` — **hijau**.
- Job `deploy` CI sengaja nonaktif sampai Secret `PROD_*` diisi; jalur manual runbook ini tidak butuh secret tersebut.
- PHP 8.4 (image `php:8.4-fpm-bookworm`), Postgres 16, Redis 7.