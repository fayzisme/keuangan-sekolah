# PRODUCTION.md — Runbook Cutover Pilot → Produksi

> Dokumen pendamping `RUNBOOK.md`. Bertujuan memandu langkah **menaikkan sistem dari pilot `:8082` menjadi produksi resmi** (`https://<domain>`) dengan CI/CD otomatis + GHCR + HTTPS + backup ketat.
>
> **Status saat dokumen ditulis:** pilot live di `http://43.173.7.25:8082/` (build lokal, deploy manual via `deploy/deploy.sh`, tanpa TLS). Pipeline CI sudah ada di `.github/workflows/ci.yml` tetapi **belum tersambung** ke server (SSH tertutup, secret belum diisi, image frontend belum di-GHCR).

---

## 1. Target Arsitektur Produksi

```
                         ┌────────────────────────────────────────────┐
   Push ke main          │           GitHub Actions (ci.yml)          │
   (ci.yml terpicu)      │                                            │
   ────────────────────▶ │  backend   : Pint + composer audit + Pest  │
                         │  frontend  : lint + build web/dist         │
                         │  build-image: docker build → PUSH ke GHCR  │
                         │  deploy    : SSH ke VPS → compose pull     │
                         │             → migrate → up -d → healthz    │
                         └───────────────┬────────────────────────────┘
                                         │ 1. pull image
                                         ▼
                        g h c r . i o / f a y z i s m e / k e u a n g a n - s e k o l a h - a p p
                                         │ 2. jalankan
                                         ▼
   ┌───────────────────────────────  VPS 43.173.7.25  ───────────────────────────────┐
   │  nginx  (80/443, HTTPS, reverse proxy)                                          │
   │    ├── app        (Laravel php-fpm, image dari GHCR)                            │
   │    ├── worker     (php artisan queue:work)                                      │
   │    ├── scheduler  (php artisan schedule:work)                                   │
   │    ├── postgres   (volume pgdata)                                               │
   │    └── redis      (volume redisdata)                                            │
   └───────────────────────────────────────────────────────────────────────────────┘
```

Perbedaan kunci diringkas:

| Aspek | Pilot (sekarang) | Produksi (target) |
|---|---|---|
| Sumber image | build lokal `school-app:pilot` | `ghcr.io/fayzisme/keuangan-sekolah-app:sha-<short>` |
| Deploy | manual `deploy/deploy.sh` | otomatis via `ci.yml` saat push ke `main` |
| Alamat | `http://…:8082` | `https://<domain>` (443) |
| HTTP→HTTPS | ❌ | redirect 301 |
| `APP_DEBUG` | `true` | `false` |
| Cache/session | `file` | `redis` |
| Queue | `sync` | `redis` + worker + scheduler |
| Path repo (RUNBOOK) | `/opt/data/school-finance-system` | `/srv/school-finance` (sesuai RUNBOOK/bootstrap.sh) |

---

## 2. Prasyarat (harus disediakan oleh pemilik sistem)

| No | Kebutuhan | Keterangan |
|---|---|---|
| 1 | **Domain** + kontrol DNS | mis. `keuangan-sekolah.my.id`; buat record `A` → `43.173.7.25` |
| 2 | **SSH port 22 terbuka** di VPS | saat ini `Connection refused` (dari tes sebelumnya); perlu buka di firewall penyedia/UFW |
| 3 | **Akun GitHub** (sudah ada: `fayzisme`) dengan akses ke repo | untuk mengisi secret + melihat Packages |
| 4 | **Kunci SSH deploy** (pasangan public/private) | public → `/home/deploy/.ssh/authorized_keys`; private → disimpan sebagai secret GitHub |

> Semua item prasyarat adalah hal yang **tidak bisa saya kerjakan dari sisi kode** — butuh tindakan dari pemilik (beli/arahkan domain, buka port, buat key). Sisanya (perubahan file repo, penyesuaian CI, menjalankan perintah di VPS) bisa saya bantu.

---

## 3. Fase A — Domain & DNS

1. Beli/daftarkan domain (atau subdomain) di registrar manapun.
2. Di panel DNS, buat:
   ```
   A        @       43.173.7.25
   A        www     43.173.7.25      (opsional, jika mau redirect)
   ```
3. Tunggu propagasi DNS (beberapa menit–jam). Verifikasi:
   ```bash
   dig +short <domain>
   host <domain>
   curl -v http://<domain>/ --max-time 10
   ```

---

## 4. Fase B — Buka SSH & Siapkan User Deploy

Pilot sekarang **tidak** punya user `deploy`; kontainer dikelola sebagai user `hermes` langsung di host. Untuk produksi ikuti alur RUNBOOK Phase 0–1 (`deploy/bootstrap.sh` disiapkan untuk ini).

1. Buka port 22 di firewall VPS (dari panel penyedia ATAU UFW yang dijalankan `bootstrap.sh`).
2. Buat user deploy:
   ```bash
   sudo useradd -m -s /bin/bash deploy
   sudo mkdir -p /home/deploy/.ssh
   sudo touch /home/deploy/.ssh/authorized_keys
   sudo chown -R deploy:deploy /home/deploy/.ssh
   sudo chmod 700 /home/deploy/.ssh && sudo chmod 600 /home/deploy/.ssh/authorized_keys
   ```
3. Tambahkan public key:
   ```bash
   echo 'ssh-ed25519 AAAA… <komentar>' | sudo tee -a /home/deploy/.ssh/authorized_keys
   ```
4. Uji dari mesin yang memegang private key:
   ```bash
   ssh -i ~/.ssh/<private> deploy@43.173.7.25
   ```
5. (Opsional) Jalankan `sudo bash deploy/bootstrap.sh` untuk hardening: key-only SSH, UFW 22/80/443, fail2ban, unattended-upgrades.

> ⚠️ Urutan operasional: lakukan backup pilot `deploy/backup.sh` **sebelum** mengubah firewall/SSH.

---

## 5. Fase C — Isi GitHub Secrets

Pipeline `ci.yml` membaca 4 secret. Isi di **GitHub → repo `keuangan-sekolah` → Settings → Secrets and variables → Actions**:

| Secret | Nilai |
|---|---|
| `PROD_HOST` | `43.173.7.25` |
| `PROD_USER` | `deploy` |
| `PROD_SSH_KEY` | isi **private key** deploy (format PEM, mulai `-----BEGIN …`) |
| `PROD_DOMAIN` | `<domain>` (mis. `keuangan-sekolah.my.id`) |

> Secret tidak bisa dibaca kembali setelah disimpan; simpan salinan private key di tempat aman (mis. password manager).

---

## 6. Fase D — HTTPS Let's Encrypt

Template sudah ada: `deploy/nginx/https.conf.example` (TLS 1.2+, HSTS, redirect 80→443).

1. Install certbot di VPS:
   ```bash
   sudo apt install -y certbot
   ```
2. Terbitkan sertifikat **webroot** (butuh nginx pilot yang melayani `/.well-known`, atau pakai mode `--nginx` setelah stack produksi up):
   ```bash
   sudo certbot certonly --webroot -w /opt/data/school-finance-system/web/dist \
     -d <domain> --agree-tos --no-eff-email --email <email>
   ```
3. Salin template menjadi konfig aktif:
   ```bash
   cp deploy/nginx/https.conf.example deploy/nginx/https.conf
   sed -i 's/<DOMAIN>/<domain>/g' deploy/nginx/https.conf
   ```
4. Perbarui `deploy/Dockerfile.web` (atau mount) agar memuat `https.conf` dan `/etc/letsencrypt`. Opsi paling sederhana di compose: gunakan volume:
   ```yaml
   nginx:
     ports: ["80:80", "443:443"]
     volumes:
       - ./deploy/nginx/https.conf:/etc/nginx/conf.d/https.conf:ro
       - /etc/letsencrypt:/etc/letsencrypt:ro
   ```
5. Aktifkan HSTS hanya setelah HTTPS terbukti stabil (baris `add_header Strict-Transport-Security …` di `https.conf.example`).
6. Verifikasi:
   ```bash
   curl -fsS https://<domain>/healthz | jq .        # -> status ok
   curl -sI https://<domain>/ | grep -i strict      # -> ada header HSTS (jika diaktifkan)
   ```

> Aksen: aturan "tanpa sentuh 80/443" **hanya berlaku untuk fase pilot**. Produksi justru WAJIB memakai 80/443.

---

## 7. Fase E — Sambungkan CI/CD (perubahan di repo)

Saat ini `ci.yml` sudah punya 4 job (backend, frontend, build-image, deploy). Tiga celah di bawah **sudah ditutup** di repo (lihat perubahan terakhir pada `ci.yml`, `docker-compose.yml`, dan `.env.example`):

### 7.1. Build image frontend (nginx) ke GHCR ✅
`ci.yml` hanya membangun `-app`. Tambahkan rilis image nginx dari `deploy/Dockerfile.web`:

```yaml
      - name: Build & push nginx image
        uses: docker/build-push-action@v6
        with:
          context: ./
          file: deploy/Dockerfile.web
          push: true
          tags: |
            ghcr.io/${{ github.repository }}-nginx:${{ steps.meta.outputs.tag }}
            ghcr.io/${{ github.repository }}-nginx:latest
          cache-from: type=gha
          cache-to: type=gha,mode=max
```

### 7.2. Sinkronkan nama image di `docker-compose.yml` ✅
Service `nginx` di compose sekarang memakai image GHCR (`ghcr.io/${GITHUB_REPOSITORY}-nginx:${APP_IMAGE_TAG}`), seragam dengan `app`/`worker`/`scheduler`. Variabel dikirim dari workflow deploy:
```yaml
      - name: Deploy via SSH
        env:
          GITHUB_REPOSITORY: ${{ github.repository }}
          APP_IMAGE_TAG: ${{ needs.build-image.outputs.tag }}
```
`.env.example` juga sudah menyertakan `GITHUB_REPOSITORY` sebagai nilai default.

### 7.3. Path server & port ✅ (via secret opsional)
Job deploy kini memakai `cd "${PROD_PATH:-/srv/school-finance}"`. Nilai default mengikuti RUNBOOK (`/srv/school-finance`); jika ingin tetap memakai `/opt/data/school-finance-system`, cukup set secret/var `PROD_PATH`. Port produksi tetap 80/443 (bukan 8082 pilot).

### 7.4. Jalankan worker & scheduler ⏳ (saat cutover)
Service `worker` & `scheduler` sudah ada di compose (butuh `QUEUE_CONNECTION=redis`). Saat cutover produksi, pastikan keduanya ikut `up -d` dan `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis` di `.env`.

> ✅ Setelah celah ditutup, **push ke `main` otomatis**: tes → build image (app+nginx) → push GHCR → SSH deploy → healthz.

---

## 8. Fase F — Env Produksi & Migrasi

1. Backup dahulu:
   ```bash
   ./deploy/backup.sh
   ```
2. Buat `.env` produksi:
   ```bash
   cp .env.example .env
   ```
   Lalu isi minimal:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://<domain>
   FRONTEND_URL=https://<domain>
   SANCTUM_STATEFUL_DOMAINS=<domain>
   APP_KEY=base64:<generate php artisan key:generate>
   PLATFORM_KEY=<openssl rand -hex 32>
   DB_DATABASE=school_finance
   DB_USERNAME=school_finance
   DB_PASSWORD=<kuat, openssl rand -base64 24>
   REDIS_PASSWORD=<openssl rand -hex 16>
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=redis
   GITHUB_REPOSITORY=fayzisme/keuangan-sekolah
   APP_IMAGE_TAG=sha-<short>
   BACKUP_DIR=/var/backups/school-finance
   BACKUP_KEEP_DAYS=30
   ```
3. Jalankan migrasi & naikkan stack:
   ```bash
   docker compose run --rm app php artisan migrate --force
   docker compose up -d --build
   docker compose ps    # semua Up (healthy)
   ```

---

## 9. Fase G — Backup & Monitoring Ketat

- **Cron harian** (RUNBOOK Phase 8, 02:00 WIB = 19:00 UTC):
  ```bash
  sudo crontab -e
  # baris berikut:
  0 19 * * * cd /srv/school-finance && ./deploy/backup.sh >> /var/log/school-backup.log 2>&1
  ```
- **Enkripsi backup** (opsional): set `GPG_KEY_ID` di `.env` → `backup.sh` otomatis mengenkripsi dengan GPG.
- **Backup off-site**: salin `/var/backups/school-finance/school-*.sql.gz` ke storage lain (rclone ke S3/Drive/dll) — jangan hanya di VPS yang sama.
- **Uji restore bulanan** wajib — "cadangan tanpa uji-restore bukan cadangan" (`backup.sh`).
- **Monitoring**: Netdata (sudah terpasang di VPS) + alarm `/healthz`; RUNBOOK Phase 9.

---

## 10. Fase H — Cutover & Verifikasi

1. Pastikan produksi verified: `/healthz` ok, login admin ok, smoke test fitur.
2. Nonaktifkan/hentikan pilot di `:8082` (hapus container `school-nginx`/`app` lama atau `docker stop`).
3. Update DNS/domain sudah mengarah ke HTTPS.
4. Uji dari perangkat lain (tidak dari VPS): `https://<domain>/`.
5. Catat as-built di `deploy/RUNBOOK.md` seksi 16.

---

## 11. Rollback

Jika produksi bermasalah:

```bash
# 1. Kembalikan ke tag image lama (immutable, dari GHCR)
export APP_IMAGE_TAG=sha-<tag-aman>
docker compose up -d pull app worker scheduler   # untuk compose GHCR
# ATAU jalankan ulang builder lokal pilot:
./deploy/deploy.sh --tag pilot

# 2. Restore database dari backup harian (jika perlu)
cd /srv/school-finance
gunzip -c /var/backups/school-finance/school-<stamp>.sql.gz | \
  docker compose exec -T postgres psql -U school_finance -d school_finance
```

---

## 12. Checklist Akhir Produksi

- [ ] Domain + DNS A record → IP VPS (terverifikasi `dig`)
- [ ] SSH port 22 terbuka, user `deploy` key-only (bukan root)
- [ ] 4 secret GitHub terisi (`PROD_HOST`, `PROD_USER`, `PROD_SSH_KEY`, `PROD_DOMAIN`)
- [ ] Sertifikat Let's Encrypt valid; HTTP→HTTPS redirect; HSTS aktif
- [ ] `ci.yml` membangun & push image `-app` **dan** `-nginx` ke GHCR
- [ ] Docker Compose memakai image GHCR (`GITHUB_REPOSITORY` + `APP_IMAGE_TAG` di-set)
- [ ] `worker` + `scheduler` jalan; `QUEUE_CONNECTION=redis`
- [ ] `.env` produksi: `APP_DEBUG=false`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`
- [ ] Backup cron harian aktif; uji restore bulanan berjalan
- [ ] Monitoring `/healthz` + alert aktif
- [ ] Pilot `:8082` dihentikan setelah produksi verified
- [ ] As-built record diisi di RUNBOOK seksi 16

---

## 13. Yang Sudah Ada vs yang Perlu Disediakan

| Kebutuhan | Status |
|---|---|
| Pipeline CI `ci.yml` (backend, frontend, build-image app+nginx, deploy) | ✅ ada (celah §7 ditutup) |
| Template HTTPS `deploy/nginx/https.conf.example` | ✅ ada |
| Script backup `deploy/backup.sh` | ✅ ada |
| Script bootstrap `deploy/bootstrap.sh` | ✅ ada |
| Docker Compose lengkap (nginx, app, worker, scheduler, postgres, redis) | ✅ ada, image GHCR |
| Script deploy manual `deploy/deploy.sh` | ✅ ada |
| **Domain + DNS** | ❌ perlu disediakan |
| **SSH port 22 + user deploy + key** | ❌ perlu disediakan |
| **GitHub secrets (4 + opsional `PROD_PATH`)** | ❌ perlu diisi |
| Image `-nginx` di GHCR | ✅ siap (otomatis di-push saat push `main` berikutnya) |
| Path repo produksi (`/srv/school-finance` atau via `PROD_PATH`) | ⏳ keputusan pemilik saat cutover |

---

*Dokumen ini hidup — perbarui setiap kali ada perubahan arsitektur. Seksi As-Built utama tetap di `deploy/RUNBOOK.md`.*