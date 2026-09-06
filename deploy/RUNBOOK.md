# RUNBOOK — Deploy & Operasi VPS School Finance

> **Versi:** 2.0 (Complete) · **Target:** VPS `43.173.7.25` · Ubuntu 24.04 LTS · 4vCPU/8GB (rekomendasi)
> **Repo:** https://github.com/fayzisme/keuangan-sekolah (public, branch `main`)
> **Stack:** nginx 1.27 · Laravel 12 php-fpm 8.4 · PostgreSQL 16 · Redis 7 · queue worker + scheduler
>
> Jalur deploy dua opsi: **A — manual runbook** (dokument ini), **B — CI GitHub Actions** (job `deploy`, butuh secrets).

---

## 1. Arsitektur (apa yang di-deploy)

```
Internet ──► UFW (22,80,443 only)
               └─► nginx:1.27 (TLS + reverse proxy + rate limit + security headers)
                     └─► app: php:8.4-fpm (Laravel 12, non-root www-data, port internal 9000)
                     └─► worker:  php artisan queue:work  (job lambat, retry 3x backoff 10s)
                     └─► scheduler: php artisan schedule:work  (invoice, reminder, rekonsiliasi)
                     └─► postgres:16-alpine (PORTER 5432 TIDAK exposé ke host)
                     └─► redis:7-alpine (PORTER 6379 TIDAK exposé ke host)
Volumes: pgdata (DB), redisdata (AOF), app_storage (upload bukti, log, session)
```

- **DB/Redis internal-only** (`appnet` bridge); **nginx** semata yang expose :80/:443.
- **Image immutable**: build 1x di CI (GHCR) atau lokal `docker compose build`; di-deploy image yang **sama**, bukan build di server (hasil CI hash-pinned `sha-<short>`).

---

## 2. Prasyarat (di laptop)

| # | Item | Catatan |
|---|---|---|
| 1 | Domain + DNS A record `<DOMAIN>` → `43.173.7.25` | Wajib propagar ≥ 5 min sebelum §8 HTTPS |
| 2 | SSH key laptop | `ssh-keygen -t ed25519 -C laptop` bila belum punya |
| 3 | Akses root VPS (SSH root / console panel) | Safety net bila SSH hardening mis-lock |
| 4 | Repo konfigurasi | `git clone https://github.com/fayzisme/keuangan-sekolah` (public) |

---

## 3. Phase 0 — Registrasi akses deploy (keystone!)

Kalau deploy dijalankan dari automasi (CI / agent sandbox), **public key automasi wajib** di `authorized_keys` **root** VPS:

```bash
# (di VPS — via console panel ATAU ssh root dulu)
echo 'ssh-ed25519 AAAA... <komentar>' >> /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
```

Verifikasi dari sender automasi:

```bash
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes root@43.173.7.25 'echo OK && id'
```

| Role di VPS | Akses | Pakai untuk |
|---|---|---|
| `root` | bootstrap (satu kali) + recovery | `deploy/bootstrap.sh` meng-hardening SSH |
| `deploy` | semua operasi harian (clone, compose, backup) | runbook §4–§11 |

---

## 4. Phase 1 — Bootstrap server (root, satu kali)

```bash
ssh root@43.173.7.25

# 4a. Buat user deploy + groups
adduser deploy && usermod -aG sudo deploy
usermod -aG docker deploy          # WAJIB: backup.sh + CI compose jalankan `docker compose` polos

# 4b. SSH key (laptop ATAU automasi) -> deploy
mkdir -p /home/deploy/.ssh
echo 'ssh-ed25519 AAAA... <komentar>' > /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys

# 4c. Jalankan bootstrap (idempotent, aman diulang)
curl -fsSL https://raw.githubusercontent.com/fayzisme/keuangan-sekolah/main/deploy/bootstrap.sh -o /tmp/bootstrap.sh
chmod +x /tmp/bootstrap.sh
sudo bash /tmp/bootstrap.sh
```

### 4d. Apa yang bootstrap.sh konfigure (ceklist)

| Item | Cek |
|---|---|
| User `deploy` (non-root, sudo docker compose scoped) | `id deploy` |
| UFW: `OpenSSH` (22) + `80/tcp` + `443/tcp` | `sudo ufw status` |
| fail2ban (SSH brute-force) | `sudo fail2ban-client status sshd` |
| unattended-upgrades (OS security auto) | `systemctl is-active unattended-upgrades` |
| Docker Engine + Compose plugin | `docker --version && docker compose version` |
| SSH hardening: `PasswordAuthentication no`, `PermitRootLogin no` | `sudo sshd -T \| grep -E 'passwordauthentication\|permitrootlogin'` |

> ⚠️ **Guard built-in**: hardening SSH aktif **hanya bila** `/home/deploy/.ssh/authorized_keys` non-empty. Test SEBELUM kelar root: `exit` poi `ssh deploy@43.173.7.25`.

### Validation Phase 1

```bash
ssh deploy@43.173.7.25 'echo OK; sudo ufw status | grep -E "22|80|443" | wc -l'
# → OK + 3 (baris rule UFW)
```

---

## 5. Phase 2 — Clone repo + build frontend (user deploy)

```bash
ssh deploy@43.173.7.25
sudo apt update && sudo apt install -y git jq
sudo mkdir -p /srv/school-finance && sudo chown deploy:deploy /srv/school-finance
git clone https://github.com/fayzisme/keuangan-sekolah.git /srv/school-finance
cd /srv/school-finance
```

**`web/dist` di-gitignore → wajib di-build** (nginx mount `./web/dist`; tanpa build → SPA 404):

| Opsi | Perintah | Note |
|---|---|---|
| **A. Build di laptop, scp** | `npm ci && npm run build` pui `scp -r web/dist deploy@IP:/srv/school-finance/web/` | bila laptop punya node |
| **B. Build di server via Docker** | `mkdir -p web/dist && docker run --rm -v /srv/school-finance:/app -w /app/web node:20-alpine sh -c 'npm ci && npm run build && ls dist/index.html'` | tanpa node di host (rekomendasi) |

### Validation Phase 2

```bash
test -s web/dist/index.html && echo "dist OK: $(du -sh web/dist | cut -f1)"
```

---

## 6. Phase 3 — `.env` secret produksi (solo di server)

```bash
cd /srv/school-finance
cp .env.example .env
nano .env            # isi sesuai tabel
chmod 600 .env
```

| Variable | Generate | Regola |
|---|---|---|
| `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"` | AES-256-CBC (32 byte exact; invalid bila ≠ 32) |
| `DB_PASSWORD` | `openssl rand -base64 24` | kuat min 16 char; protege data seluruh sekolah |
| `REDIS_PASSWORD` | `openssl rand -hex 16` | |
| `PLATFORM_KEY` | `openssl rand -hex 32` | header `X-Platform-Key`; kosong → onboarding 503 (amat) |
| `APP_URL` | `https://<DOMAIN>` | |
| `FRONTEND_URL` | `https://<DOMAIN>` | CORS Sanctum |
| `SANCTUM_STATEFUL_DOMAINS` | `<DOMAIN>` | stateful cookie domain |

> ⚠️ **`.env` tidak pernah masuk git** (gitignore blok. `.env.*` kecuali `.env.example`).

---

## 7. Phase 4 — Build image + migrate + up stack

```bash
cd /srv/school-finance
docker compose build app                         # build image + composer install in stage vendor
docker compose up -d postgres redis              # DB/Redis piu dulu (healthcheck start)
docker compose run --rm app php artisan migrate --force
docker compose up -d                             # nginx app worker scheduler
docker compose ps
```

### Urutan korekta (kenapa ini order)

1. **build app** — image name `ghcr.io/owner/repo-app:latest` (default `GITHUB_REPOSITORY`) → **localhost build wajib** bila tidak ada GHCR image; `compose up` akan try pull & gagal.
2. **up postgres redis** — migrate butuh DB up & healthy.
3. **migrate SEBELUM app up** — app boot butuh schema siap (foreign keys, partial indexes).
4. **up -d** — order service stack (nginx depends_on app healthy).

### Validation Phase 4

```bash
docker compose ps --format 'table {{.Name}}\t{{.Service}}\t{{.State}}\t{{.Status}}'
# semua State=Up; app status (healthy); postgres/redis healthy
docker compose logs --tail=30 app     # geen error boot; "Server started"
```

---

## 8. Phase 5 — Health check + smoke

```bash
curl -s http://localhost/healthz | jq .
# → {"status":"ok","database":"ok","cache":"ok", ...} (routes/web.php /healthz)

curl -s http://localhost/api/v1/ping | jq .
# → {"status":"ok","message":"School Finance API v1 siap."}
```

Semua **HTTP 200**. Bila 500/connection → §12 Troubleshooting.

---

## 9. Phase 6 — Pilot sekolah pertama (onboarding)

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
# → 201 {school:{id,name}, admin:{id,name,email}} — transazione atomica:
#   School + User + pivot active + role:admin team-scope (OnboardSchoolAction)
```

Test login + RBAC:

```bash
TOKEN=$(curl -s -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@pilot.sch.id","password":"S3cur3-Passw0rd!"}' | jq -r .token)
curl -s http://localhost/api/v1/auth/me -H "Authorization: Bearer $TOKEN" | jq .
curl -s http://localhost/api/v1/classes -H "Authorization: Bearer $TOKEN" | jq .   # admin → 200 []
```

### Jalur uji fungsional end-to-end

login → master data (academic year, kelas, siswa, guardian) → bill-types → `POST /invoices/generate` → payment manual `POST /payments/manual` (Idempotency-Key) → verify `POST /payments/{id}/verify` (maker ≠ checker) → report `/reports/student/{id}` → export PDF `/exports/student/{id}/pdf` + Excel `/exports/arrears/excel`.

---

## 10. Phase 7 — HTTPS Let's Encrypt

```bash
cd /srv/school-finance
sudo apt install -y certbot
mkdir -p web/dist/.well-known
sudo certbot certonly --webroot -w /srv/school-finance/web/dist \
  -d <DOMAIN> --agree-tos --no-eff-email -m admin@<DOMAIN>

sed 's/<DOMAIN>/<DOMAIN>/g' deploy/nginx/https.conf.example > deploy/nginx/default.conf
docker compose restart nginx
```

### Validation Phase 7

```bash
curl -fsS https://<DOMAIN>/healthz | jq .     # 200
curl -sI https://<DOMAIN> | grep -iE 'strict-transport|content-security'
curl -sI https://<DOMAIN> | grep '^HTTP'       # 200 (no HSTS on plain redirect)
```

> HSTS `max-age=1y` aktif di template — jangan dimatikan, bila HTTPS terbukti stabil. Redirect :80→:443 di blok bawah template.

---

## 11. Phase 8 — Backup harian + uji restore (wajib bulanan)

```bash
crontab -e    # user deploy
# 02:00 WIB = 19:00 UTC
0 19 * * * cd /srv/school-finance && ./deploy/backup.sh >> /var/log/school-finance-backup.log 2>&1

# test imediato
./deploy/backup.sh && ls -lh /var/backups/school-finance/
```

**Uji restore bulanan (RPO ≤ 24h, RTO ≤ 4h):**

```bash
gzip -dk /var/backups/school-finance/school-<stempel>.sql.gz
docker compose exec -T -e PGPASSWORD="$DB_PASSWORD" postgres \
  psql -U "$DB_USERNAME" -d "$DB_DATABASE" < /var/backups/school-finance/school-<stempel>.sql
# bandingkan count baris tabel kunci (users, schools, invoices, payments) produksi vs restore
```

> Enkripsi: set `GPG_KEY_ID` in `.env` + uncomment blok `gpg` in `deploy/backup.sh`.

---

## 12. Phase 9 — Monitoring & operasional

| Keperluan | Alat / Perintah |
|---|---|
| Uptime | Uptime Kuma / UptimeRobot → `https://<DOMAIN>/healthz` interval 5 min, alert Telegram |
| Error tracking | Sentry (free tier) — SDK PHP + JS, `DSN` in `.env` |
| Log app | `docker compose logs -f --tail=100 app` |
| Log worker/scheduler | `docker compose logs --tail=50 worker` · `--tail=50 scheduler` |
| Retry queue gagal | `docker compose run --rm app php artisan queue:retry all` |
| Disk & cert | `df -h` · Uptime Kuma cert expiry · `certbot renew --dry-run` |

---

## 13. Phase 10 — Update & rollback

```bash
# ---- Update (urutan aman) ----
cd /srv/school-finance
git pull
docker run --rm -v /srv/school-finance:/app -w /app/web node:20-alpine sh -c 'npm ci && npm run build'
./deploy/backup.sh                                   # backup SEBELUM migrate
docker compose run --rm app php artisan migrate --force
docker compose up -d --build
curl -fsS https://<DOMAIN>/healthz | jq .

# ---- Rollback (image build lokal) ----
git fetch --tags && git checkout <tag-sebelumnya>
docker compose up -d --build

# ---- Rollback (image GHCR pinned — opsi CI) ----
# .env: APP_IMAGE_TAG=sha-<pref>  GITHUB_REPOSITORY=fayzisme/keuangan-sekolah
docker compose pull app worker scheduler
docker compose up -d
```

---

## 14. Troubleshooting (error → fix)

| Symptom | Root cause | Fix |
|---|---|---|
| `docker compose up` gagal pull `ghcr.io/owner/repo-app` | Image belum di-push GHCR (no CI) | `docker compose build app` local, atau set `GITHUB_REPOSITORY` + tag |
| `/healthz` 500 `Target class [files] does not exist` | `config/app.php` men-set `'providers' => []` (masalah lama, fixed di commit `22ab455`) | update repo + rebuild |
| `composer install` gagal `ext-gd` | PHP stage vendor ≠ runtime ext | Dockerfile sudah fix (stage vendor `php:8.4-cli` + ext gd), rebuild |
| `Pest: test directory tests/Unit not found` | `tests/Unit` kosong hilang dari git | fixed commit `e25a244` (.gitkeep), pull latest |
| 401 login loop | Sanctum stateful cookie domain `/ SANCTUM_STATEFUL_DOMAINS` | cek `.env` value |
| Migrate hang | Postgres belum healthy | `docker compose up -d postgres` → `docker compose ps` wait healthy |
| PHP fatal `---> Target class [files]` saat artisan | `config/app.php` providers key | sure `config/app.php` bersih (no `providers` key) |
| Backup `pg_dump` fail | Postgres down ATAU password env | `docker compose ps`; `source .env` sebelum script |
| SSL cert not validate | DNS belum propagar / webroot challenge | cek `dig <DOMAIN>`; certbot webroot path abso |

---

## 15. Checklist keamanan (final)

- [ ] `sudo ufw status` — alleen 22/80/443
- [ ] SSH key-only + root login off — `ssh deploy@43.173.7.25` vanaf laptop/agent
- [ ] `sudo fail2ban-client status sshd` — active
- [ ] `.env` perm `600`, niet in git (`git ls-files | grep -c .env` = 1 (.env.example))
- [ ] `docker compose ps` — geen `0.0.0.0:5432` / `0.0.0.0:6379` (DB/Redis internal)
- [ ] Backup harian jalan + restore test gedaan
- [ ] healthz monitor aktief (Uptime Kuma)
- [ ] HSTS active (na HTTPS stabil)
- [ ] `.env` APP_DEBUG=false

---

## 16. As-Built Record (diisi setelah deploy)

| Item | Value |
|---|---|
| Tanggal deploy | 2026-09-06 (pilot) |
| Domain live | (belum — port alternatif pilot) |
| Commit hash (code) | `050e810` (RUNBOOK v2.0) |
| Image tag build | `school-app:pilot`, `school-nginx:pilot` |
| VPS IP | `43.173.7.25` (host sandbox/Docker) |
| OS | Ubuntu (host) — VM-3-246-ubuntu |
| Pilot school name/id | SMA Pilot Test / id 1 |
| Admin pilot | `admin@pilot.test` |
| Verifier pilot | `verifier@bank.test` (bendahara) |
| Backup cron | `0 19 * * *` (02:00 WIB) |
| Monitor | belum |

## 17. Verifikasi Live Pilot (2026-09-06)

Stack pilot: `school-pg` (Postgres 16) · `school-redis` (Redis 7) · `app` (php-fpm 8.4) · `school-nginx` (port 8082 host). Semua flow terverifikasi HTTP dari host bridge `172.17.0.1:8082`:

| Check | Hasil |
|---|---|
| `GET /healthz` | `{"status":"ok","checks":{"app":"ok","database":"ok","cache":"ok"}}` |
| `GET /api/v1/ping` | `{"status":"ok","message":"School Finance API v1 siap."}` |
| SPA `/` | HTTP 200 |
| Onboarding `POST /platform/schools` (X-Platform-Key) | 201, school id 1 + admin id 1 |
| Login + `/me` | token Sanctum, roles `[admin]`, active_school id 1 |
| CRUD master data | academic year, class, bill-types (monthly/one_time), students, guardian + attach primary |
| Generate invoice | SPP Sept 2026 (2x 150k) + Uang Pangkal (2x 2,5jt) = 4 invoice OPEN |
| Payment manual (Idempotency-Key) | PENDING_VERIFICATION, `CASH-…` trx id |
| Maker-checker verify | maker (admin) ≠ checker (bendahara) → SETTLED; invoice 1 → `PAID` |
| Report student | total 2.650.000, dibayar 150.000, sisa 2.500.000 |
| Export PDF/Excel/CSV | student.pdf `%PDF-`, arrears.pdf `%PDF-`, arrears.xlsx `PK`, arrears.csv (baris NIS benar) |
| RBAC isolation | bendahara `POST /students` → 403 (write = admin) |

### Catatan adaptasi pilot (berbeda dari prod normal)

1. **Port alternatif** — 80/443 host dipakai layanan lain (nginx + Netdata). Pilot di `:8082` melalui image nginx baked (`web/dist` + config di-copy ke image, bukan volume — bind-mount rusak di sandbox).
2. **App container bernama `app`** — nginx default.conf meng-upstream `app:9000`; nama harus persis.
3. **Config/bake** — storage via volume `app_storage`; env via `-e` (bukan file `.env` volume).
4. **Verifier user** dibentuk via SQL bootstrap (bukan endpoint) — alur pilot; di produksi, tambah user via API/CRUD.
5. `.env` prod (`APP_KEY`, `DB_PASSWORD`, `PLATFORM_KEY`, `REDIS_PASSWORD`) dipuasakan dengan `-e` per container.

Rollback pilot: `docker rm -f school-nginx app school-redis school-pg && docker volume rm school_pgdata school_redisdata` + `docker network rm school-net`.

---

*End of RUNBOOK v2.0 — dokumentasi lengkap deploy manual VPS + verifikasi live pilot. Opsi CI (Phase 10 rollback GHCR) memakai .github/workflows/ci.yml job `deploy` (secrets `PROD_HOST/PROD_USER/PROD_SSH_KEY/PROD_DOMAIN`).*